<?php

declare(strict_types=1);

namespace Indieinabox\SiteBuilder;

use Indieinabox\Markdown\LanguageProcessor;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Site;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface;
use Indieinabox\Taxonomy\TaxonomyService;
use Indieinabox\Translations\UrlTranslations;
use RuntimeException;

/**
 * Handles translation parity and virtualization of missing pages across languages.
 */
class TranslationVirtualizer
{
    private Site $site;
    private ?SettingsRepositoryInterface $settingsRepo;
    private TaxonomyServiceInterface $taxonomyService;
    private \Indieinabox\Localization\Contracts\TranslationEngineInterface $translationEngine;

    public function __construct(
        Site $site,
        ?SettingsRepositoryInterface $settingsRepo = null,
        ?TaxonomyServiceInterface $taxonomyService = null,
        ?\Indieinabox\Localization\Contracts\TranslationEngineInterface $translationEngine = null
    ) {
        $this->site = $site;
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        $this->settingsRepo = $settingsRepo ?? (($container && $container->has(SettingsRepositoryInterface::class))
            ? $container->get(SettingsRepositoryInterface::class)
            : null);

        if ($taxonomyService !== null) {
            $this->taxonomyService = $taxonomyService;
        } elseif ($container && $container->has(TaxonomyServiceInterface::class)) {
            $this->taxonomyService = $container->get(TaxonomyServiceInterface::class);
        } else {
            $this->taxonomyService = new TaxonomyService($this->site);
        }

        if ($translationEngine !== null) {
            $this->translationEngine = $translationEngine;
        } elseif ($container && $container->has(\Indieinabox\Localization\Contracts\TranslationEngineInterface::class)) {
            $this->translationEngine = $container->get(\Indieinabox\Localization\Contracts\TranslationEngineInterface::class);
        } else {
            $config = Database::getAllSettings();
            $aiProviderName = strtolower(trim((string)($config['ai_translation_provider'] ?? '')));
            $aiApiKey = trim((string)($config['ai_translation_api_key'] ?? ''));
            $aiModel = trim((string)($config['ai_translation_model'] ?? ''));
            $aiEndpoint = trim((string)($config['ai_translation_endpoint'] ?? ''));

            $aiProvider = null;
            if ($aiProviderName === 'gemini') {
                $aiProvider = new \Indieinabox\Localization\Providers\GeminiProvider($aiApiKey, $aiModel ?: 'gemini-1.5-flash');
            } elseif ($aiProviderName === 'openai') {
                $aiProvider = new \Indieinabox\Localization\Providers\OpenAiCompatibleProvider(
                    'openai',
                    'OpenAI GPT',
                    $aiEndpoint ?: 'https://api.openai.com/v1/chat/completions',
                    $aiApiKey,
                    $aiModel ?: 'gpt-4o-mini'
                );
            } elseif ($aiProviderName === 'grok') {
                $aiProvider = new \Indieinabox\Localization\Providers\OpenAiCompatibleProvider(
                    'grok',
                    'xAI Grok',
                    $aiEndpoint ?: 'https://api.x.ai/v1/chat/completions',
                    $aiApiKey,
                    $aiModel ?: 'grok-beta'
                );
            } elseif ($aiProviderName === 'claude') {
                $aiProvider = new \Indieinabox\Localization\Providers\ClaudeProvider($aiApiKey, $aiModel ?: 'claude-3-5-haiku-20241022');
            } elseif ($aiProviderName === 'ollama') {
                $aiProvider = new \Indieinabox\Localization\Providers\OpenAiCompatibleProvider(
                    'ollama',
                    'Ollama (Local)',
                    $aiEndpoint ?: 'http://localhost:11434/v1/chat/completions',
                    '',
                    $aiModel ?: 'llama3.2'
                );
            }

            $dictEngine = new \Indieinabox\Localization\Engines\DictionaryTranslationEngine();
            // Auto-load project locales if available
            $dataDir = Database::$dataDir !== '' ? Database::$dataDir : dirname(__DIR__, 2) . '/data';
            $localesDir = $dataDir . '/locales';
            if (is_dir($localesDir)) {
                $files = glob($localesDir . '/*.json');
                if (is_array($files)) {
                    foreach ($files as $f) {
                        $l = pathinfo($f, PATHINFO_FILENAME);
                        $dictEngine->loadDictionaryFile($l, $f);
                    }
                }
            }

            if ($aiProvider !== null && $aiProvider->isAvailable()) {
                $this->translationEngine = new \Indieinabox\Localization\Engines\AiTranslationEngine($aiProvider, null, $dictEngine);
            } else {
                $this->translationEngine = $dictEngine;
            }
        }
    }

    /**
     * Generates pseudo-translated pages for missing languages to maintain parity.
     * Uses configured rules (e.g., full parity, from-main-only) and translates
     * missing slugs according to URL translation mappings.
     *
     * @param Pages $pages
     * @return void
     */
    public function virtualize(Pages $pages): void
    {
        $langs = (array) ($this->site->localization->lang ?? ['en']);
        if (count($langs) <= 1) {
            return;
        }

        $defaultLang = (string) ($this->site->localization->defaultLang ?? 'en');
        $prettylinks = (bool) ($this->site->options->prettylinks ?? true);

        $parity = (string) ($this->site->options->translation_parity ?? 'full');
        if ($parity === 'disabled') {
            return;
        }
        $autoVirtualize = (string) ($this->site->options->translation_auto ?? 'pseudo');

        /** @var array<string, array<string, string>> $urlTranslationsArr */
        $urlTranslationsArr = (array) ($this->site->config['urltranslations'] ?? (
            $this->settingsRepo ? $this->settingsRepo->getUrlTranslations() : Database::getUrlTranslations()
        ));
        $reverseTranslations = [];
        foreach ($urlTranslationsArr as $defaultNick => $translations) {
            foreach ($translations as $l => $translatedNick) {
                $reverseTranslations[$l][$translatedNick] = $defaultNick;
            }
        }

        $existing = [];
        $pagesToProcess = [];
        foreach ($pages as $page) {
            $lang = $page->lang ?? $defaultLang;
            $nick = $page->nick ?? '';
            $kind = $page->kind ?? '';

            $existing["{$kind}:{$nick}:{$lang}"] = $page;
            $pagesToProcess[] = $page;
        }

        foreach ($pagesToProcess as $page) {
            if (in_array($page->kind, ['generic'], true)) {
                if ($page->slug !== '' && $page->slug !== 'index.html' && $page->slug !== '/') {
                    continue;
                }
            }

            $sourceLang = $page->lang ?? $defaultLang;

            // Find base nick
            $baseNick = $page->nick;
            if ($sourceLang !== $defaultLang) {
                if (isset($reverseTranslations[$sourceLang][$page->nick])) {
                    $baseNick = $reverseTranslations[$sourceLang][$page->nick];
                }
            }

            $sourceIsMain = ($sourceLang === $defaultLang);

            foreach ($langs as $targetLang) {
                if ($targetLang === $sourceLang) {
                    continue;
                }

                $targetIsMain = ($targetLang === $defaultLang);

                if ($parity === 'from-main-only' && !$sourceIsMain) {
                    continue;
                }
                if ($parity === 'from-sublang-only' && $sourceIsMain) {
                    continue;
                }
                if ($parity === 'inter-sublang-only' && ($sourceIsMain || $targetIsMain)) {
                    continue;
                }

                $targetNick = $baseNick;
                if ($targetLang !== $defaultLang) {
                    if (isset($urlTranslationsArr[$baseNick][$targetLang])) {
                        $targetNick = $urlTranslationsArr[$baseNick][$targetLang];
                    }
                }

                $key = "{$page->kind}:{$targetNick}:{$targetLang}";
                if (!isset($existing[$key])) {
                    if ($autoVirtualize === 'disabled') {
                        throw new RuntimeException(
                            "Translation Parity rule '{$parity}' violated. " .
                            "Missing translation for '{$page->slug}' in '{$targetLang}'."
                        );
                    }

                    $existing[$key] = true; // Mark as handled

                    if (php_sapi_name() === 'cli') {
                        echo "[WARNING] Missing translation for page '{$page->slug}'"
                            . " in language '{$targetLang}'. Virtualizing...\n";
                    }

                    $cloned = clone $page;
                    $cloned->lang = $targetLang;
                    $cloned->nick = $targetNick;

                    // Set automated AI translation attribution flag on virtualized pages
                    if (is_object($cloned->metadata)) {
                        $cloned->metadata->translated_by = 'ai';
                        $cloned->metadata->translation_source_lang = $sourceLang;
                    }

                    $this->pseudoTranslate($cloned, $targetLang, $sourceLang);

                    $kindFolder = $this->taxonomyService->getKindFolder($cloned->kind, $targetLang);
                    $sourceKindFolder = $this->taxonomyService->getKindFolder($page->kind, $sourceLang);

                    if (in_array($sourceKindFolder, ['page', 'generic', 'home'], true)) {
                        $sourceKindFolder = '';
                    }
                    if (in_array($kindFolder, ['page', 'generic', 'home'], true)) {
                        $kindFolder = '';
                    }

                    $cleanSlug = trim($page->slug, '/');
                    $sourceLangPrefix = $sourceLang !== $defaultLang ? $sourceLang . '/' : '';
                    $sourcePrefix = $sourceLangPrefix . $sourceKindFolder;
                    $sourcePrefix = trim($sourcePrefix, '/');

                    if ($sourcePrefix !== '' && str_starts_with($cleanSlug, $sourcePrefix . '/')) {
                        $cleanSlug = substr($cleanSlug, strlen($sourcePrefix . '/'));
                    } elseif ($sourcePrefix !== '' && $cleanSlug === $sourcePrefix) {
                        $cleanSlug = '';
                    }

                    if ($cleanSlug === '' || $cleanSlug === 'index.html') {
                        $cloned->slug = $targetLang !== $defaultLang ? $targetLang . '/index.html' : 'index.html';
                    } else {
                        $targetPrefix = $targetLang !== $defaultLang ? $targetLang . '/' : '';
                        if ($kindFolder !== '') {
                            $targetPrefix .= $kindFolder . '/';
                        }

                        if ($prettylinks) {
                            $cloned->slug = $targetPrefix . $cleanSlug . '/';
                        } else {
                            if (str_ends_with($cleanSlug, '.html')) {
                                $cleanSlug = substr($cleanSlug, 0, -5);
                            }
                            $cloned->slug = $targetPrefix . $cleanSlug . '.html';
                        }
                    }

                    $cloned->slug = trim(str_replace('//', '/', $cloned->slug), '/');
                    if (
                        $prettylinks
                        && !str_ends_with($cloned->slug, '.html')
                        && $cloned->slug !== ''
                        && $cloned->slug !== 'index.html'
                    ) {
                        $cloned->slug .= '/';
                    }

                    $cleanSlugPath = ltrim($cloned->slug, '/');
                    if ($cleanSlugPath === '' || $cleanSlugPath === 'index.html') {
                        $cloned->relpath = './';
                    } else {
                        $slashCount = substr_count($cleanSlugPath, '/');
                        $cloned->relpath = $slashCount > 0 ? str_repeat('../', $slashCount) : './';
                    }

                    $urlTranslationsObj = new UrlTranslations($urlTranslationsArr);
                    $languageProcessor = new LanguageProcessor($this->site, $urlTranslationsObj);
                    $cloned = $languageProcessor->processLanguage($cloned);

                    $pages->add($cloned);
                }
            }
        }
    }

    /**
     * Applies a pseudo-translation prefix to a page's title or content.
     * Used visually to flag that a page was automatically virtualized.
     *
     * @param Page $page The page to translate in place.
     * @param string $targetLang The target language code used as the prefix.
     * @return void
     */
    /**
     * Translates a page in place using the configured TranslationEngine.
     * Preserves frontmatter, code blocks, and HTML tags while translating title and content.
     *
     * @param Page $page The page to translate in place.
     * @param string $targetLang The target language code.
     * @param string $sourceLang The source language code.
     * @return void
     */
    public function pseudoTranslate(Page $page, string $targetLang, string $sourceLang = 'en'): void
    {
        $hasTitle = !empty($page->title)
            && $page->title !== 'Untitled'
            && $page->title !== 'untitled';

        $kindConfig = $this->taxonomyService->getKindConfig($page->kind);
        if (isset($kindConfig['has_title']) && !$kindConfig['has_title']) {
            $hasTitle = false;
        }

        if ($hasTitle) {
            $page->title = $this->translationEngine->translateText($page->title, $targetLang, $sourceLang);
            // When translating with AI/MT, translate full post body as well as title
            if ($this->translationEngine instanceof \Indieinabox\Localization\Engines\AiTranslationEngine) {
                $page->content->content = $this->translationEngine->translateContent($page->content->content, $targetLang, $sourceLang);
                $page->content->rawBody = $this->translationEngine->translateContent($page->content->rawBody, $targetLang, $sourceLang);
            }
        } else {
            $page->content->content = $this->translationEngine->translateContent($page->content->content, $targetLang, $sourceLang);
            $page->content->rawBody = $this->translationEngine->translateContent($page->content->rawBody, $targetLang, $sourceLang);
        }
    }

    /**
     * Returns the underlying translation engine.
     */
    public function getTranslationEngine(): \Indieinabox\Localization\Contracts\TranslationEngineInterface
    {
        return $this->translationEngine;
    }

    /**
     * Sets a custom translation engine.
     */
    public function setTranslationEngine(\Indieinabox\Localization\Contracts\TranslationEngineInterface $engine): void
    {
        $this->translationEngine = $engine;
    }

    /**
     * Retrieves the taxonomy service instance.
     *
     * @return \Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface
     */
    public function getTaxonomyService(): TaxonomyServiceInterface
    {
        return $this->taxonomyService;
    }
}
