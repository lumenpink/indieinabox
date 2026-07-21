<?php

declare(strict_types=1);

namespace Indieinabox\Theme;

use Indieinabox\Page;
use Indieinabox\Site;
use Indieinabox\Helper;

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
        $bg = '#F4F1EA';
        $fg = '#2C2E2F';

        $kindConfig = Helper::getKindConfig($kind);
        if (!empty($kindConfig['palette'])) {
            $bg = $kindConfig['palette']['bg'] ?? $bg;
            $fg = $kindConfig['palette']['fg'] ?? $fg;
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
        $seo = Helper::getSeoMetadata($page);
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
        $seo = Helper::getSeoMetadata($page);
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
        $seo = Helper::getSeoMetadata($page);
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
        $seo = Helper::getSeoMetadata($page);
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
            $label = strtoupper($l);
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
        $navItems[] = '<a href="' . htmlspecialchars($homeLink) . '">' . Helper::translate('Home') . '</a>';
        $navItems[] = '<a href="' . htmlspecialchars($indexLink) . '">' . Helper::translate('Index') . '</a>';
        
        foreach ($headerLinks as $item) {
            $navItems[] = '<a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['label']) . '</a>';
        }

        return '<nav class="top-nav" aria-label="Main navigation" style="text-align: center;">' . "\n" .
               '    [ ' . implode(' &bull; ', $navItems) . ' ]' . "\n" .
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
}
