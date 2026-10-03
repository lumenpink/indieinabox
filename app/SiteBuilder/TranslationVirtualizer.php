<?php

declare(strict_types=1);

namespace Indieinabox\SiteBuilder;

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Localization\AiModels;
use Indieinabox\Localization\Engines\AiTranslationEngine;
use Indieinabox\Localization\Engines\DictionaryTranslationEngine;
use Indieinabox\Localization\Providers\ClaudeProvider;
use Indieinabox\Localization\Providers\GeminiProvider;
use Indieinabox\Localization\Providers\OpenAiCompatibleProvider;
use Indieinabox\Localization\Repositories\PersistentTaxonomyTranslationRepository;
use Indieinabox\Localization\Services\AiResponseSanitizer;
use Indieinabox\Localization\Services\PositionalLinkMasker;
use Indieinabox\Markdown\LanguageProcessor;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Site\Site;
use Indieinabox\Support\TextParser;
use Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface;
use Indieinabox\Taxonomy\TaxonomyService;
use Indieinabox\Translations\UrlTranslations;
use RuntimeException;
use Throwable;

/**
 * Handles translation parity and virtualization of missing pages across languages.
 * Implements a robust Two-Pass pipeline:
 *  - Pass 1: Translates and persists titles, slugs, flowerbeds (canteiros), tags, and categories in SQLite.
 *  - Pass 2: Masks content links (rewriting internal cross-links), translates pure Markdown body without frontmatter,
 *    sanitizes outputs, and restores links strictly by position.
 */
class TranslationVirtualizer
{
    private Site $site;
    private ?SettingsRepositoryInterface $settingsRepo;
    private TaxonomyServiceInterface $taxonomyService;
    private \Indieinabox\Localization\Contracts\TranslationEngineInterface $translationEngine;
    private PersistentTaxonomyTranslationRepository $taxonomyRepo;
    private ?string $aiProviderName = null;
    private ?string $aiModelName = null;

    public function __construct(
        Site $site,
        ?SettingsRepositoryInterface $settingsRepo = null,
        ?TaxonomyServiceInterface $taxonomyService = null,
        ?\Indieinabox\Localization\Contracts\TranslationEngineInterface $translationEngine = null,
        ?PersistentTaxonomyTranslationRepository $taxonomyRepo = null
    ) {
        $this->site = $site;
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        $this->settingsRepo = $settingsRepo ?? (($container && $container->has(SettingsRepositoryInterface::class))
            ? $container->get(SettingsRepositoryInterface::class)
            : null);

        $this->taxonomyRepo = $taxonomyRepo ?? new PersistentTaxonomyTranslationRepository();

        if ($taxonomyService !== null) {
            $this->taxonomyService = $taxonomyService;
        } elseif ($container && $container->has(TaxonomyServiceInterface::class)) {
            $this->taxonomyService = $container->get(TaxonomyServiceInterface::class);
        } else {
            $this->taxonomyService = new TaxonomyService($this->site);
        }

        $config = Database::getAllSettings();
        $this->aiProviderName = strtolower(trim((string)($config['ai_translation_provider'] ?? '')));
        $this->aiModelName = trim((string)($config['ai_translation_model'] ?? ''));

        if ($translationEngine !== null) {
            $this->translationEngine = $translationEngine;
        } elseif ($container && $container->has(\Indieinabox\Localization\Contracts\TranslationEngineInterface::class)) {
            $this->translationEngine = $container->get(\Indieinabox\Localization\Contracts\TranslationEngineInterface::class);
        } else {
            $aiApiKey = trim((string)($config['ai_translation_api_key'] ?? ''));
            $aiEndpoint = trim((string)($config['ai_translation_endpoint'] ?? ''));

            $aiProvider = null;
            if ($this->aiProviderName === 'gemini') {
                $aiProvider = new GeminiProvider(
                    $aiApiKey,
                    $this->aiModelName ?: AiModels::getDefaultModel('gemini')
                );
            } elseif ($this->aiProviderName === 'openai') {
                $aiProvider = new OpenAiCompatibleProvider(
                    'openai',
                    'OpenAI GPT',
                    $aiEndpoint ?: 'https://api.openai.com/v1/chat/completions',
                    $aiApiKey,
                    $this->aiModelName ?: AiModels::getDefaultModel('openai')
                );
            } elseif ($this->aiProviderName === 'grok') {
                $aiProvider = new OpenAiCompatibleProvider(
                    'grok',
                    'xAI Grok',
                    $aiEndpoint ?: 'https://api.x.ai/v1/chat/completions',
                    $aiApiKey,
                    $this->aiModelName ?: AiModels::getDefaultModel('grok')
                );
            } elseif ($this->aiProviderName === 'claude') {
                $aiProvider = new ClaudeProvider(
                    $aiApiKey,
                    $this->aiModelName ?: AiModels::getDefaultModel('claude')
                );
            } elseif ($this->aiProviderName === 'ollama') {
                $aiProvider = new OpenAiCompatibleProvider(
                    'ollama',
                    'Ollama (Local)',
                    $aiEndpoint ?: 'http://localhost:11434/v1/chat/completions',
                    '',
                    $this->aiModelName ?: AiModels::getDefaultModel('ollama')
                );
            }

            $dictEngine = new DictionaryTranslationEngine();
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
                $this->translationEngine = new AiTranslationEngine($aiProvider, null, $dictEngine);
            } else {
                $this->translationEngine = $dictEngine;
            }
        }
    }

    /**
     * Generates translated virtualized pages across languages.
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

        // =========================================================================
        // PASS 1: Pre-translate & Persist Titles, Slugs, Flowerbeds, Tags, Categories
        // =========================================================================
        $this->pass1PretranslateTaxonomiesAndSlugs(
            $pagesToProcess,
            $langs,
            $defaultLang,
            $parity,
            $existing,
            $urlTranslationsArr,
            $reverseTranslations
        );

        // =========================================================================
        // PASS 2: Page Virtualization, Cross-link Rewriting & Content Translation
        // =========================================================================
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

                    // Set automated AI translation attribution tags in frontmatter metadata
                    if (is_object($cloned->metadata)) {
                        $cloned->metadata->translated_by = 'ai';
                        $cloned->metadata->translated_by_ia = true;

                        $providerLabel = $this->getAiProviderLabel();
                        $modelName = $this->aiModelName;
                        if ($this->translationEngine instanceof AiTranslationEngine) {
                            $provider = $this->translationEngine->getProvider();
                            $providerLabel = $provider->getLabel();
                            if (method_exists($provider, 'getModelName')) {
                                $modelName = $provider->getModelName();
                            }
                        }

                        $cloned->metadata->translation_ai_provider = $providerLabel;
                        $cloned->metadata->translation_ai_model = $modelName ?: 'gemini-2.5-flash';
                        $cloned->metadata->translation_source_lang = $sourceLang;
                        $cloned->metadata->translation_date = date('Y-m-d');
                    }

                    // Apply pre-translated taxonomies (flowerbeds, tags, categories)
                    $this->applyTranslatedTaxonomies($cloned, $targetLang, $sourceLang);

                    // Translate content with positional link masking
                    $this->pseudoTranslate($cloned, $targetLang, $sourceLang, $urlTranslationsArr);

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

                        $slugSegment = ($targetNick !== $baseNick) ? $targetNick : $cleanSlug;

                        if ($prettylinks) {
                            $cloned->slug = $targetPrefix . $slugSegment . '/';
                        } else {
                            if (str_ends_with($slugSegment, '.html')) {
                                $slugSegment = substr($slugSegment, 0, -5);
                            }
                            $cloned->slug = $targetPrefix . $slugSegment . '.html';
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
     * Pass 1: Translates and persists all structural metadata (Titles, Slugs, Flowerbeds, Tags, Categories).
     */
    private function pass1PretranslateTaxonomiesAndSlugs(
        array $pagesToProcess,
        array $langs,
        string $defaultLang,
        string $parity,
        array $existing,
        array &$urlTranslationsArr,
        array &$reverseTranslations
    ): void {
        if (!($this->translationEngine instanceof AiTranslationEngine)) {
            return;
        }
        foreach ($pagesToProcess as $page) {
            $sourceLang = $page->lang ?? $defaultLang;
            $sourceIsMain = ($sourceLang === $defaultLang);

            $baseNick = $page->nick;
            if ($sourceLang !== $defaultLang && isset($reverseTranslations[$sourceLang][$page->nick])) {
                $baseNick = $reverseTranslations[$sourceLang][$page->nick];
            }

            foreach ($langs as $targetLang) {
                if ($targetLang === $sourceLang) {
                    continue;
                }

                $targetIsMain = ($targetLang === $defaultLang);
                if ($parity === 'from-main-only' && !$sourceIsMain) continue;
                if ($parity === 'from-sublang-only' && $sourceIsMain) continue;
                if ($parity === 'inter-sublang-only' && ($sourceIsMain || $targetIsMain)) continue;

                // 1. Translate Title & Slug if missing
                if (!isset($urlTranslationsArr[$baseNick][$targetLang])) {
                    $cachedSlug = $this->taxonomyRepo->get($sourceLang, $targetLang, 'title_slug', $baseNick);
                    if ($cachedSlug !== null && $cachedSlug !== '') {
                        $urlTranslationsArr[$baseNick][$targetLang] = $cachedSlug;
                        $reverseTranslations[$targetLang][$cachedSlug] = $baseNick;
                    } else {
                        // Translate title to derive slug
                        $title = (string) ($page->title ?? $baseNick);
                        if ($title !== '' && $title !== 'Untitled' && $title !== 'untitled') {
                            $translatedTitle = $this->translationEngine->translateText($title, $targetLang, $sourceLang);
                            $translatedSlug = TextParser::slugize($translatedTitle);
                        } else {
                            $translatedSlug = $baseNick;
                        }

                        $this->taxonomyRepo->set($sourceLang, $targetLang, 'title_slug', $baseNick, $translatedSlug);
                        $this->persistUrlTranslationToDb($targetLang, $baseNick, $translatedSlug);

                        $urlTranslationsArr[$baseNick][$targetLang] = $translatedSlug;
                        $reverseTranslations[$targetLang][$translatedSlug] = $baseNick;
                    }
                }

                // 2. Pre-translate Flowerbeds (Canteiros)
                if (!empty($page->metadata->flowerbed)) {
                    $flowerbeds = is_array($page->metadata->flowerbed) ? $page->metadata->flowerbed : [$page->metadata->flowerbed];
                    foreach ($flowerbeds as $fb) {
                        if (is_string($fb) && trim($fb) !== '') {
                            $cachedFb = $this->taxonomyRepo->get($sourceLang, $targetLang, 'flowerbed', $fb);
                            if ($cachedFb === null) {
                                $transFb = $this->translationEngine->translateText($fb, $targetLang, $sourceLang);
                                $this->taxonomyRepo->set($sourceLang, $targetLang, 'flowerbed', $fb, $transFb);
                            }
                        }
                    }
                }

                // 3. Pre-translate Tags
                if (!empty($page->metadata->tags)) {
                    $tags = is_array($page->metadata->tags) ? $page->metadata->tags : [$page->metadata->tags];
                    foreach ($tags as $tag) {
                        if (is_string($tag) && trim($tag) !== '' && $tag !== 'draft') {
                            $cachedTag = $this->taxonomyRepo->get($sourceLang, $targetLang, 'tag', $tag);
                            if ($cachedTag === null) {
                                $transTag = $this->translationEngine->translateText($tag, $targetLang, $sourceLang);
                                $cleanTag = TextParser::slugize($transTag);
                                $this->taxonomyRepo->set($sourceLang, $targetLang, 'tag', $tag, $cleanTag);
                            }
                        }
                    }
                }

                // 4. Pre-translate Category
                if (!empty($page->category) && is_string($page->category)) {
                    $cat = trim($page->category);
                    $cachedCat = $this->taxonomyRepo->get($sourceLang, $targetLang, 'category', $cat);
                    if ($cachedCat === null) {
                        $transCat = $this->translationEngine->translateText($cat, $targetLang, $sourceLang);
                        $this->taxonomyRepo->set($sourceLang, $targetLang, 'category', $cat, $transCat);
                    }
                }
            }
        }
    }

    /**
     * Applies pre-translated taxonomies onto a cloned page.
     */
    private function applyTranslatedTaxonomies(Page $cloned, string $targetLang, string $sourceLang): void
    {
        // Flowerbeds
        if (!empty($cloned->metadata->flowerbed)) {
            $flowerbeds = is_array($cloned->metadata->flowerbed) ? $cloned->metadata->flowerbed : [$cloned->metadata->flowerbed];
            $translatedFbs = [];
            foreach ($flowerbeds as $fb) {
                if (is_string($fb)) {
                    $mapped = $this->taxonomyRepo->get($sourceLang, $targetLang, 'flowerbed', $fb);
                    $translatedFbs[] = $mapped ?: $fb;
                }
            }
            $cloned->metadata->flowerbed = $translatedFbs;
        }

        // Tags
        if (!empty($cloned->metadata->tags)) {
            $tags = is_array($cloned->metadata->tags) ? $cloned->metadata->tags : [$cloned->metadata->tags];
            $translatedTags = [];
            foreach ($tags as $tag) {
                if ($tag === 'draft') {
                    $translatedTags[] = 'draft';
                } elseif (is_string($tag)) {
                    $mapped = $this->taxonomyRepo->get($sourceLang, $targetLang, 'tag', $tag);
                    $translatedTags[] = $mapped ?: $tag;
                }
            }
            $cloned->metadata->tags = $translatedTags;
        }

        // Category
        if (!empty($cloned->category) && is_string($cloned->category)) {
            $mappedCat = $this->taxonomyRepo->get($sourceLang, $targetLang, 'category', $cloned->category);
            if ($mappedCat !== null) {
                $cloned->category = $mappedCat;
                if (isset($cloned->metadata->category)) {
                    $cloned->metadata->category = $mappedCat;
                }
            }
        }
    }

    /**
     * Translates a page in place using the configured TranslationEngine.
     * Uses PositionalLinkMasker to resolve internal cross-links and preserve links safely.
     *
     * @param Page $page
     * @param string $targetLang
     * @param string $sourceLang
     * @param array<string, array<string, string>> $urlTranslationsArr
     * @return void
     */
    public function pseudoTranslate(
        Page $page,
        string $targetLang,
        string $sourceLang = 'en',
        array $urlTranslationsArr = []
    ): void {
        $hasTitle = !empty($page->title)
            && $page->title !== 'Untitled'
            && $page->title !== 'untitled';

        $kindConfig = $this->taxonomyService->getKindConfig($page->kind);
        if (isset($kindConfig['has_title']) && !$kindConfig['has_title']) {
            $hasTitle = false;
        }

        if ($hasTitle) {
            $page->title = $this->translationEngine->translateText($page->title, $targetLang, $sourceLang);
        }

        $rawBody = (string) ($page->content->rawBody ?? $page->content->content ?? '');

        if ($this->translationEngine instanceof AiTranslationEngine) {
            $defaultLang = (string) ($this->site->localization->defaultLang ?? 'en');

            // Positional link resolver callback for rewriting internal links
            $urlResolver = function (string $url) use ($targetLang, $defaultLang, $urlTranslationsArr): string {
                if (preg_match('~^(https?://|mailto:|tel:|#)~i', $url)) {
                    return $url;
                }

                $path = parse_url($url, PHP_URL_PATH) ?? $url;
                $query = parse_url($url, PHP_URL_QUERY);
                $fragment = parse_url($url, PHP_URL_FRAGMENT);

                foreach ($urlTranslationsArr as $srcNick => $transNicks) {
                    if (isset($transNicks[$targetLang])) {
                        $translatedSlug = $transNicks[$targetLang];
                        if (str_contains($path, '/' . $srcNick . '.') || str_contains($path, '/' . $srcNick . '/') || str_ends_with($path, '/' . $srcNick)) {
                            $path = str_replace($srcNick, $translatedSlug, $path);
                            break;
                        }
                    }
                }

                if ($targetLang !== $defaultLang) {
                    $cleanPath = ltrim($path, '/');
                    if (!str_starts_with($cleanPath, $targetLang . '/')) {
                        $path = '/' . $targetLang . '/' . $cleanPath;
                    }
                }

                $finalUrl = $path;
                if ($query) $finalUrl .= '?' . $query;
                if ($fragment) $finalUrl .= '#' . $fragment;
                return $finalUrl;
            };

            // Mask links, images, and code blocks before sending to AI
            $masked = PositionalLinkMasker::mask($rawBody, $urlResolver);

            // Translate pure body without frontmatter
            $translatedContent = $this->translationEngine->translateContent($masked['maskedContent'], $targetLang, $sourceLang);

            // Sanitize conversational filler
            $sanitized = AiResponseSanitizer::sanitize($translatedContent);

            // Restore links strictly by position
            $restored = PositionalLinkMasker::restore($sanitized, $masked['tokens']);

            $page->content->content = $restored;
            $page->content->rawBody = $restored;
        } elseif (!$hasTitle) {
            // Dictionary engine / Fallback: only translate body when page has no title
            $page->content->content = $this->translationEngine->translateContent($rawBody, $targetLang, $sourceLang);
            $page->content->rawBody = $this->translationEngine->translateContent($rawBody, $targetLang, $sourceLang);
        }
    }

    private function persistUrlTranslationToDb(string $lang, string $slugKey, string $slugValue): void
    {
        try {
            $db = Database::getDb();
            if ($db) {
                $stmt = $db->prepare('INSERT OR REPLACE INTO url_translations (lang, slug_key, slug_value) VALUES (?, ?, ?)');
                $stmt->execute([$lang, $slugKey, $slugValue]);
            }
        } catch (Throwable) {
            // Graceful fallback
        }
    }

    private function getAiProviderLabel(): string
    {
        return match ($this->aiProviderName) {
            'gemini' => 'Google Gemini',
            'openai' => 'OpenAI GPT',
            'claude' => 'Anthropic Claude',
            'grok' => 'xAI Grok',
            'ollama' => 'Ollama (Local)',
            default => 'AI',
        };
    }

    public function getTranslationEngine(): \Indieinabox\Localization\Contracts\TranslationEngineInterface
    {
        return $this->translationEngine;
    }

    public function setTranslationEngine(\Indieinabox\Localization\Contracts\TranslationEngineInterface $engine): void
    {
        $this->translationEngine = $engine;
    }

    public function getTaxonomyService(): TaxonomyServiceInterface
    {
        return $this->taxonomyService;
    }

    public function getTaxonomyRepo(): PersistentTaxonomyTranslationRepository
    {
        return $this->taxonomyRepo;
    }
}
