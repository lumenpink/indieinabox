<?php

declare(strict_types=1);

namespace Indieinabox\SiteBuilder;

use Indieinabox\Markdown\LanguageProcessor;
use Indieinabox\Taxonomy\KindHelper;
use Indieinabox\Page;
use Indieinabox\Pages;
use Indieinabox\Site;
use Indieinabox\Translations\UrlTranslations;
use RuntimeException;

/**
 * Handles translation parity and virtualization of missing pages across languages.
 */
class TranslationVirtualizer
{
    private Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
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

        global $urltranslations;
        /** @var array<string, array<string, string>> $urlTranslationsArr */
        $urlTranslationsArr = $urltranslations ?? [];
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

                    $this->pseudoTranslate($cloned, $targetLang);

                    $kindFolder = KindHelper::getKindFolder($cloned->kind, $targetLang);
                    $sourceKindFolder = KindHelper::getKindFolder($page->kind, $sourceLang);

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
    public function pseudoTranslate(Page $page, string $targetLang): void
    {
        $prefix = '[' . strtoupper($targetLang) . '] ';
        $hasTitle = !empty($page->title)
            && $page->title !== 'Untitled'
            && $page->title !== 'untitled';

        $kindConfig = KindHelper::getKindConfig($page->kind);
        if (isset($kindConfig['has_title']) && !$kindConfig['has_title']) {
            $hasTitle = false;
        }

        if ($hasTitle) {
            $page->title = $prefix . $page->title;
        } else {
            $page->content->content = $prefix . $page->content->content;
            $page->content->rawBody = $prefix . $page->content->rawBody;
        }
    }
}
