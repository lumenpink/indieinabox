<?php

declare(strict_types=1);

namespace Indieinabox\Theme;

use Indieinabox\Core\Database;
use Indieinabox\Localization\IsoLanguages;
use Indieinabox\Localization\Translator;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Site;
use Indieinabox\Support\TextParser;
use Indieinabox\Taxonomy\KindHelper;
use Indieinabox\Taxonomy\TaxonomyService;

/**
 * Class ThemeData
 *
 * Provides autonomous methods to generate template data and markup for themes,
 * decoupling low-level logic from presentation views.
 */
class ThemeData
{
    /**
     * Gets the formatted page title.
     *
     * @param Page $page
     * @param Site $site
     * @return string
     */
    public static function getPageTitle(Page $page, Site $site): string
    {
        $sitename = $site->metadata->sitename ?? '';
        if (empty($page->title) || $page->title === 'Untitled') {
            return $sitename;
        }
        return $page->title . ' | ' . $sitename;
    }

    /**
     * Gets the calculated theme colors based on the page kind.
     *
     * @param Page $page
     * @return array<string, string> An array containing 'bg' and 'fg' color hex codes.
     */
    public static function getThemeColors(Page $page): array
    {
        $kind = strtolower($page->kind ?? 'generic');
        $default = \Indieinabox\Taxonomy\TaxonomyService::getDefaultPalette($kind);
        $bg = $default['bg'];
        $fg = $default['fg'];

        $kindConfig = KindHelper::getKindConfig($kind);
        if (!empty($kindConfig['palette']) && !empty($kindConfig['palette']['bg']) && !empty($kindConfig['palette']['fg'])) {
            $bg = $kindConfig['palette']['bg'];
            $fg = $kindConfig['palette']['fg'];
        }

        return ['bg' => $bg, 'fg' => $fg];
    }

    /**
     * Generates the standard meta tags (description, canonical, shortlink).
     *
     * @param Page $page
     * @param Site $site
     * @return string HTML meta tags.
     */
    public static function getMetaTags(Page $page, Site $site): string
    {
        $seo = KindHelper::getSeoMetadata($page);
        $baseUrl = rtrim($site->metadata->fqdn ?? '', '/');
        $pageUrl = $baseUrl . '/' . ltrim($page->relpath ?? '', '/');

        $html = '<meta name="description" content="' . htmlspecialchars($seo['description']) . '">' . "\n";
        $html .= '<link rel="canonical" href="' . htmlspecialchars($pageUrl) . '">' . "\n";
        
        if (!empty($page->shortlink)) {
            $html .= '<link rel="shortlink" href="' . htmlspecialchars($page->shortlink) . '">' . "\n";
        }

        return $html;
    }

    /**
     * Generates OpenGraph meta tags.
     *
     * @param Page $page
     * @param Site $site
     * @return string HTML meta tags.
     */
    public static function getOpenGraphTags(Page $page, Site $site): string
    {
        $seo = KindHelper::getSeoMetadata($page);
        $baseUrl = rtrim($site->metadata->fqdn ?? '', '/');
        $pageUrl = $baseUrl . '/' . ltrim($page->relpath ?? '', '/');
        
        $imageInfo = pathinfo($seo['image']);
        $ogImage = $imageInfo['dirname'] . '/' . $imageInfo['filename'] . '_1200x630.png';

        $type = in_array($seo['schema_type'], ['BlogPosting', 'Article', 'SocialMediaPosting', 'Comment']) ? 'article' : 'website';

        $html = '<meta property="og:site_name" content="' . htmlspecialchars($site->metadata->sitename ?? 'Blog') . '" />' . "\n";
        $html .= '<meta property="og:type" content="' . htmlspecialchars($type) . '" />' . "\n";
        $html .= '<meta property="og:title" content="' . htmlspecialchars(self::getPageTitle($page, $site)) . '" />' . "\n";
        $html .= '<meta property="og:description" content="' . htmlspecialchars($seo['description']) . '" />' . "\n";
        $html .= '<meta property="og:url" content="' . htmlspecialchars($pageUrl) . '" />' . "\n";
        $html .= '<meta property="og:image" content="' . htmlspecialchars($ogImage) . '" />' . "\n";
        $html .= '<meta property="og:image:alt" content="' . htmlspecialchars($seo['image_alt']) . '" />' . "\n";

        if (!empty($page->isodate)) {
            $html .= '<meta property="article:published_time" content="' . htmlspecialchars($page->isodate) . '" />' . "\n";
        }
        $html .= '<meta property="article:author" content="' . htmlspecialchars($site->metadata->author ?? '') . '" />' . "\n";

        return $html;
    }

    /**
     * Generates Twitter Card meta tags.
     *
     * @param Page $page
     * @param Site $site
     * @return string HTML meta tags.
     */
    public static function getTwitterCardTags(Page $page, Site $site): string
    {
        $seo = KindHelper::getSeoMetadata($page);
        $imageInfo = pathinfo($seo['image']);
        $ogImage = $imageInfo['dirname'] . '/' . $imageInfo['filename'] . '_1200x630.png';

        $html = '<meta name="twitter:card" content="summary_large_image">' . "\n";
        $html .= '<meta name="twitter:title" content="' . htmlspecialchars(self::getPageTitle($page, $site)) . '">' . "\n";
        $html .= '<meta name="twitter:description" content="' . htmlspecialchars($seo['description']) . '">' . "\n";
        $html .= '<meta name="twitter:image" content="' . htmlspecialchars($ogImage) . '">' . "\n";
        $html .= '<meta name="twitter:image:alt" content="' . htmlspecialchars($seo['image_alt']) . '">' . "\n";

        return $html;
    }

    /**
     * Generates the Schema.org JSON-LD script tag.
     *
     * @param Page $page
     * @param Site $site
     * @return string HTML script tag containing JSON-LD.
     */
    public static function getJsonLd(Page $page, Site $site): string
    {
        $baseUrl = rtrim($site->metadata->fqdn ?? '', '/');
        $seo = KindHelper::getSeoMetadata($page);
        $pageUrl = $baseUrl . '/' . ltrim($page->relpath ?? '', '/');

        $imageInfo = pathinfo($seo['image']);
        $baseImageName = $imageInfo['dirname'] . '/' . $imageInfo['filename'];
        $img16x9 = $baseImageName . '_1920x1080.png';
        $img4x3 = $baseImageName . '_1440x1080.png';
        $img1x1 = $baseImageName . '_1080x1080.png';

        $jsonLd = [
            "@context" => "https://schema.org",
            "@type" => $seo['schema_type'],
            "mainEntityOfPage" => [
                "@type" => "WebPage",
                "@id" => $pageUrl
            ],
            "headline" => empty($page->title) || $page->title === 'Untitled' ? ($site->metadata->sitename ?? '') : $page->title,
            "description" => $seo['description'],
            "image" => [
                $img16x9,
                $img4x3,
                $img1x1
            ],
            "datePublished" => $page->isodate ?? date('c'),
            "dateModified" => $page->isodate ?? date('c'),
            "author" => [
                "@type" => "Person",
                "name" => $site->metadata->author ?? 'Author',
                "url" => $baseUrl
            ],
            "publisher" => [
                "@type" => "Organization",
                "name" => $site->metadata->author ?? 'Blog',
                "logo" => [
                    "@type" => "ImageObject",
                    "url" => $baseUrl . "/media/default.png"
                ]
            ]
        ];

        $json = json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return "<script type=\"application/ld+json\">\n" . $json . "\n</script>\n";
    }

    /**
     * Generates the language selector HTML markup.
     *
     * @param Page $page
     * @param Site $site
     * @param array<string, string>|null $langLinks Array of language codes to relative paths.
     * @return string HTML nav block or empty string if single language.
     */
    public static function getLanguageSelector(Page $page, Site $site, ?array $langLinks = null): string
    {
        $langs = $site->localization->lang;
        if (!is_array($langs)) {
            $langs = [$langs];
        }

        if (count($langs) <= 1) {
            return '';
        }

        $defaultLang = $site->localization->defaultLang ?? 'en';
        $currentLang = $page->lang ?? 'en';
        $displayMode = (string) ($site->localization->langDisplayMode ?? 'native_short');

        if ($langLinks === null) {
            $langLinks = [];
            foreach ($langs as $l) {
                if ($l === $defaultLang) {
                    $langLinks[$l] = $page->relpath;
                } else {
                    $langLinks[$l] = $page->relpath . $l . '/';
                }
            }
        }

        $linksHTML = [];
        foreach ($langs as $l) {
            $label = IsoLanguages::getLabel($l, $displayMode);
            if ($l === $currentLang) {
                $linksHTML[] = '<strong aria-current="true">' . htmlspecialchars($label) . '</strong>';
            } else {
                $url = $langLinks[$l] ?? '';
                if ($url !== '') {
                    $linksHTML[] = '<a href="' . htmlspecialchars($url) . '" hreflang="' . htmlspecialchars($l) . '">' . htmlspecialchars($label) . '</a>';
                }
            }
        }

        return '<nav class="lang-selector" aria-label="Language selector" style="text-align: center;">' . "\n" .
               '    [ ' . implode(' &bull; ', $linksHTML) . ' ]' . "\n" .
               '</nav>' . "\n";
    }

    /**
     * Generates the top navigation links HTML markup.
     *
     * @param Page $page
     * @param Site $site
     * @param array<int, array<string, string>> $headerLinks
     * @return string HTML nav block.
     */
    public static function getHeaderNavLinks(Page $page, Site $site, array $headerLinks = []): string
    {
        $defaultLang = $site->localization->defaultLang ?? 'en';
        $lang = $page->lang ?? 'en';
        $langPrefix = ($lang === $defaultLang) ? '' : $lang . '/';
        $prettylinks = $site->options->prettylinks ?? true;
        $indexSlugConfig = $site->config['index_slug'] ?? 'index';

        if ($prettylinks) {
            $homeLink = $page->relpath . $langPrefix;
            $indexLink = $page->relpath . $langPrefix . $indexSlugConfig . '/';
        } else {
            $homeLink = $page->relpath . ($langPrefix ? $langPrefix . 'index.html' : 'index.html');
            $indexLink = $page->relpath . $langPrefix . $indexSlugConfig . '.html';
        }

        $navItems = [];
        $navItems[] = '<a href="' . htmlspecialchars($homeLink) . '">' . Translator::translate('Home') . '</a>';
        $navItems[] = '<a href="' . htmlspecialchars($indexLink) . '">' . Translator::translate('Index') . '</a>';
        
        foreach ($headerLinks as $item) {
            $navItems[] = '<a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['label']) . '</a>';
        }

        return '<nav class="top-nav" aria-label="Main navigation" style="text-align: center;">' . "\n" .
               '    [ ' . implode(' &bull; ', $navItems) . ' ]' . "\n" .
               '</nav>' . "\n";
    }

    /**
     * Renders a navigation section listing all enabled and existing post kinds in the footer.
     *
     * @param Page $page
     * @param Pages|array<Page>|null $pages
     * @param Site|null $site
     * @return string HTML nav block or empty string if no active kinds exist.
     */
    public static function getKindLinks(Page $page, mixed $pages = null, ?Site $site = null): string
    {
        $container = class_exists(\Indieinabox\Core\Container::class) ? \Indieinabox\Core\Container::getInstance() : null;
        $site = $site ?? ($container && $container->has(Site::class) ? $container->get(Site::class) : ($GLOBALS['site'] ?? null));
        $pageCollection = $pages ?? ($container && $container->has(Pages::class) ? $container->get(Pages::class) : ($GLOBALS['pages'] ?? null));

        if ($pageCollection === null) {
            return '';
        }

        // Count posts by kind
        $kindCounts = [];
        $iterable = $pageCollection instanceof Pages ? $pageCollection->all() : (is_iterable($pageCollection) ? $pageCollection : []);
        foreach ($iterable as $p) {
            if ($p instanceof Page && !empty($p->kind)) {
                $kind = strtolower((string)$p->kind);
                $kindCounts[$kind] = ($kindCounts[$kind] ?? 0) + 1;
            }
        }

        if (empty($kindCounts)) {
            return '';
        }

        $taxonomyService = new TaxonomyService($site);
        $configuredKinds = $site && !empty($site->config['kinds']) ? $site->config['kinds'] : Database::getKinds();

        // System kinds to ignore
        $systemKinds = ['generic', 'page', 'home'];

        // Determine all active kinds that have posts and are not hidden
        $activeKinds = [];
        $candidates = array_unique(array_merge(array_keys($configuredKinds), array_keys($kindCounts)));

        foreach ($candidates as $kind) {
            $kind = strtolower((string)$kind);
            if (in_array($kind, $systemKinds, true)) {
                continue;
            }
            if (($kindCounts[$kind] ?? 0) === 0) {
                continue;
            }

            $conf = $configuredKinds[$kind] ?? $taxonomyService->getKindConfig($kind);
            if (isset($conf['show_in_menu']) && !$conf['show_in_menu']) {
                continue;
            }

            $activeKinds[] = $kind;
        }

        if (empty($activeKinds)) {
            return '';
        }

        $lang = $page->lang ?? ($site?->localization->defaultLang ?? 'pt');
        $defaultLang = $site?->localization->defaultLang ?? 'pt';
        $prettylinks = $site?->options->prettylinks ?? true;
        $langPrefix = ($lang === $defaultLang) ? '' : $lang . '/';

        $linksHTML = [];
        foreach ($activeKinds as $kind) {
            $label = $taxonomyService->getKindLabel($kind, $lang);
            $folder = $taxonomyService->getKindFolder($kind, $lang);
            $count = $kindCounts[$kind] ?? 0;
            $displayLabel = $label . ' (' . $count . ')';

            if ($prettylinks) {
                $url = ltrim($langPrefix . $folder . '/', '/');
            } else {
                $url = ltrim($langPrefix . $folder . '.html', '/');
            }
            $relUrl = $page->relpath . $url;

            $linksHTML[] = '<a href="' . htmlspecialchars($relUrl, ENT_QUOTES | ENT_HTML5) . '" class="kind-link kind-link-' . htmlspecialchars($kind, ENT_QUOTES | ENT_HTML5) . '">' . htmlspecialchars($displayLabel, ENT_QUOTES | ENT_HTML5) . '</a>';
        }

        return '<nav class="footer-kinds" aria-label="' . htmlspecialchars(Translator::translate('Post kinds', $lang), ENT_QUOTES | ENT_HTML5) . '" style="text-align: center; margin-bottom: 0.5rem;">' . "\n" .
               '    [ ' . implode(' &bull; ', $linksHTML) . ' ]' . "\n" .
               '</nav>' . "\n";
    }

    /**
     * Generates the footer links HTML markup including RSS and ATOM.
     *
     * @param Page $page
     * @param array<int, array<string, string>> $footerLinks
     * @return string HTML nav block.
     */
    public static function getFooterLinks(Page $page, array $footerLinks = []): string
    {
        $linksHTML = [];
        foreach ($footerLinks as $item) {
            $linksHTML[] = '<a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['label']) . '</a>';
        }
        $linksHTML[] = '<a href="' . htmlspecialchars($page->relpath) . 'rss.xml">RSS</a>';
        $linksHTML[] = '<a href="' . htmlspecialchars($page->relpath) . 'atom.xml">ATOM</a>';

        return '<nav class="footer-links" aria-label="Footer navigation" style="text-align: center;">' . "\n" .
               '    ' . implode(' | ', $linksHTML) . "\n" .
               '</nav>' . "\n";
    }

    /**
     * Generates the canonical 2-line multilingual footer according to GOST Profile Lumen Section 13.
     *
     * @param string $lang ISO 639-1 language code (e.g. 'pt', 'en', 'es', etc.).
     * @param string|null $appName Public application identifier (default: 'IndieInABox').
     * @param string|null $version Version string (default: Version::get()).
     * @param string|null $timestamp ISO 8601 UTC timestamp (default: current UTC time).
     * @param string|null $license License SPDX code (default: 'Apache-2.0').
     * @param string|null $licenseUrl License URL.
     * @return string
     */
    public static function getCanonicalFooter(
        string $lang = 'pt',
        ?string $appName = null,
        ?string $version = null,
        ?string $timestamp = null,
        ?string $license = 'Apache-2.0',
        ?string $licenseUrl = 'https://codeberg.org/indieinabox/indieinabox/src/branch/main/LICENSE'
    ): string {
        $appName = $appName ?? 'IndieInABox';
        $version = $version ?? \Indieinabox\Core\Version::get();
        $timestamp = $timestamp ?? gmdate('Y-m-d\TH:i:s\Z');
        $license = $license ?? 'Apache-2.0';
        $licenseUrl = $licenseUrl ?? 'https://codeberg.org/indieinabox/indieinabox/src/branch/main/LICENSE';

        $matrix = [
            'pt' => [
                'line1' => 'Orgulhosamente produzido por uma <a href="https://travesti.2lp.in/pt/">travesti latina</a> 🏳️‍⚧️ :)',
                'by' => 'por',
                'country' => 'Brasil 🇧🇷',
            ],
            'en' => [
                'line1' => 'Proudly produced by a <a href="https://travesti.2lp.in/en/">Latin American travesti</a> 🏳️‍⚧️ :)',
                'by' => 'by',
                'country' => 'Brazil 🇧🇷',
            ],
            'es' => [
                'line1' => 'Orgullosamente producido por una <a href="https://travesti.2lp.in/es/">travesti latinoamericana</a> 🏳️‍⚧️ :)',
                'by' => 'por',
                'country' => 'Brasil 🇧🇷',
            ],
            'fr' => [
                'line1' => 'Fièrement produit par une <a href="https://travesti.2lp.in/fr/">travesti latino-américaine</a> 🏳️‍⚧️ :)',
                'by' => 'par',
                'country' => 'Brésil 🇧🇷',
            ],
            'de' => [
                'line1' => 'Mit Stolz entwickelt von einer <a href="https://travesti.2lp.in/de/">lateinamerikanischen Travesti</a> 🏳️‍⚧️ :)',
                'by' => 'von',
                'country' => 'Brasilien 🇧🇷',
            ],
            'it' => [
                'line1' => 'Orgogliosamente prodotto da uma <a href="https://travesti.2lp.in/it/">travesti latinoamericana</a> 🏳️‍⚧️ :)',
                'by' => 'da',
                'country' => 'Brasile 🇧🇷',
            ],
            'ru' => [
                'line1' => 'С гордостью создано <a href="https://travesti.2lp.in/ru/">латиноамериканской travesti</a> 🏳️‍⚧️ :)',
                'by' => 'от',
                'country' => 'Бразилия 🇧🇷',
            ],
            'zh' => [
                'line1' => '由一位<a href="https://travesti.2lp.in/zh/">拉美 travesti</a> 自豪制作 🏳️‍⚧️ :)',
                'by' => '来自',
                'country' => '巴西 🇧🇷',
            ],
            'ja' => [
                'line1' => '<a href="https://travesti.2lp.in/ja/">ラテンアメリカの travesti</a> によって誇りを持って制作されました 🏳️‍⚧️ :)',
                'by' => '制作:',
                'country' => 'ブラジル 🇧🇷',
            ],
            'ar' => [
                'line1' => 'صُنع بكل فخر بواسطة <a href="https://travesti.2lp.in/ar/">travesti أمريكية لاتينية</a> 🏳️‍⚧️ :)',
                'by' => 'بواسطة',
                'country' => 'البرازيل 🇧🇷',
            ],
        ];

        $target = $matrix[$lang] ?? $matrix['pt'];
        $line1 = $target['line1'];

        $appFormatted = htmlspecialchars($appName) . ' v' . htmlspecialchars($version);
        $authorFormatted = $target['by'] . ' <a href="https://lumen.pink">Lumen Pink</a>';
        $countryFormatted = $target['country'];
        $licenseFormatted = '<a href="' . htmlspecialchars($licenseUrl) . '" target="_blank" rel="noopener">' . htmlspecialchars($license) . '</a>';

        $line2 = $appFormatted . ' &bull; ' . $authorFormatted . ' &bull; ' . htmlspecialchars($timestamp) . ' &bull; ' . $countryFormatted . ' &bull; ' . $licenseFormatted;

        $dirAttr = ($lang === 'ar') ? ' dir="rtl"' : '';

        return '<div class="footer-canonical"' . $dirAttr . ">\n" .
               '    <div class="footer-line footer-line-identity">' . $line1 . "</div>\n" .
               '    <div class="footer-line footer-line-metadata">' . $line2 . "</div>\n" .
               '</div>';
    }

    /**
     * Generates a semantic h-card markup for the site author (IndieWebify.me Level 1).
     *
     * @param Site $site
     * @return string HTML h-card block.
     */
    public static function getHCard(Site $site): string
    {
        $baseUrl = rtrim($site->metadata->fqdn ?? '', '/');
        $author = $site->metadata->author ?? ($site->metadata->sitename ?? 'Author');
        $bio = Database::getSetting('activitypub_bio') ?? ($site->metadata->description ?? '');
        $avatar = Database::getSetting('activitypub_avatar');

        $html = '<div class="h-card author-card" style="margin-bottom: 1.5em; display: flex; gap: 1em; align-items: center;">' . "\n";
        if (!empty($avatar)) {
            $avatarUrl = (strpos($avatar, 'http') === 0) ? $avatar : $baseUrl . '/' . ltrim($avatar, '/');
            $html .= '    <img class="u-photo" src="' . htmlspecialchars($avatarUrl) . '" alt="' . htmlspecialchars($author) . '" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; margin: 0;">' . "\n";
        }
        $html .= '    <div>' . "\n";
        $html .= '        <a class="p-name u-url u-uid" rel="me" href="' . htmlspecialchars($baseUrl) . '/">' . htmlspecialchars($author) . '</a>' . "\n";
        if (!empty($bio)) {
            $html .= '        <p class="p-note" style="margin: 0.2em 0; font-size: 0.9em; opacity: 0.85;">' . htmlspecialchars($bio) . '</p>' . "\n";
        }
        $html .= '    </div>' . "\n";
        $html .= '</div>' . "\n";

        return $html;
    }

    /**
     * Extracts tag frequencies and weight metadata across pages.
     *
     * @param iterable<Page> $pages
     * @param string|null $currentLang
     * @return array<string, array{tag: string, count: int, slug: string, weight: float}>
     */
    public static function getTagCloudData(iterable $pages, ?string $currentLang = null): array
    {
        $frequencies = [];

        foreach ($pages as $p) {
            if ($currentLang !== null && $p->lang !== null && $p->lang !== $currentLang) {
                continue;
            }
            if ($p->nick === 'intro' || ($p->filepath && basename($p->filepath) === 'intro.md') || in_array('draft', $p->metadata->tags ?? [])) {
                continue;
            }

            $tags = $p->metadata->tags ?? [];
            if (!is_array($tags)) {
                $tags = [$tags];
            }
            $tags = array_values(array_filter($tags, fn($t) => is_string($t) && trim($t) !== '' && $t !== 'No Tag'));

            $rawBody = (string) ($p->rawBody ?? ($p->content->rawBody ?? ''));
            if ($rawBody === '' && isset($p->content->content)) {
                $rawBody = (string) $p->content->content;
            }
            $extracted = TextParser::extractHashtags($rawBody);
            if (!empty($extracted)) {
                $tags = array_values(array_unique(array_merge($tags, $extracted)));
            }

            foreach ($tags as $tag) {
                $normTag = mb_strtolower(trim($tag));
                if ($normTag === '') {
                    continue;
                }
                $frequencies[$normTag] = ($frequencies[$normTag] ?? 0) + 1;
            }
        }

        if (empty($frequencies)) {
            return [];
        }

        uksort($frequencies, fn($a, $b) => strcasecmp((string) $a, (string) $b));
        $min = min($frequencies);
        $max = max($frequencies);
        $range = $max - $min;

        $result = [];
        foreach ($frequencies as $tag => $count) {
            $weight = $range > 0 ? (float) (($count - $min) / $range) : 0.5;
            $result[$tag] = [
                'tag' => $tag,
                'count' => $count,
                'slug' => TextParser::slugize($tag),
                'weight' => $weight,
            ];
        }

        return $result;
    }

    /**
     * Generates responsive HTML markup for a visual tag cloud.
     *
     * @param iterable<Page> $pages
     * @param Site $site
     * @param string|null $currentLang
     * @param int $minFontSizePercent Minimum font size percentage (default 85)
     * @param int $maxFontSizePercent Maximum font size percentage (default 180)
     * @return string HTML tag cloud container
     */
    public static function getTagCloud(
        iterable $pages,
        Site $site,
        ?string $currentLang = null,
        int $minFontSizePercent = 85,
        int $maxFontSizePercent = 180
    ): string {
        $tagData = self::getTagCloudData($pages, $currentLang);
        if (empty($tagData)) {
            return '';
        }

        $defaultLang = $site->localization->defaultLang ?? 'en';
        $langPrefix = ($currentLang && $currentLang !== $defaultLang) ? $currentLang . '/' : '';
        $base = '/' . $langPrefix . 'tag/';

        $items = [];
        foreach ($tagData as $info) {
            $spread = (float) ($maxFontSizePercent - $minFontSizePercent);
            $size = (int) round(((float) $minFontSizePercent) + ($info['weight'] * $spread));
            $url = $base . $info['slug'] . '/';
            $tagLabel = '#' . htmlspecialchars($info['tag']);
            $count = $info['count'];

            $items[] = sprintf(
                '<a href="%s" class="tag-cloud-item" style="font-size: %d%%; text-decoration: none;" title="%d post(s)" data-count="%d">%s</a>',
                htmlspecialchars($url),
                $size,
                $count,
                $count,
                $tagLabel
            );
        }

        return "<div class=\"tag-cloud\" role=\"navigation\" aria-label=\"Tag Cloud\" style=\"display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: baseline;\">\n    "
            . implode("\n    ", $items)
            . "\n</div>\n";
    }
}

