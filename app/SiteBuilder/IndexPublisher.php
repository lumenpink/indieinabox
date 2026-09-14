<?php

declare(strict_types=1);

namespace Indieinabox\SiteBuilder;

use DateTime;
use Indieinabox\Localization\Translator;
use Indieinabox\Page;
use Indieinabox\Pages;
use Indieinabox\Site;
use Indieinabox\Support\TextParser;
use Indieinabox\Taxonomy\KindHelper;
use Indieinabox\Theme\ThemeHelper;
use Indieinabox\ThemeManager;
use Indieinabox\Twtxt\TwtxtManager;

/**
 * Publishes index pages: section indexes, timeline indexes, taxonomies, and sitemaps.
 */
class IndexPublisher
{
    private Site $site;
    private PagePublisher $pagePublisher;

    public function __construct(Site $site, PagePublisher $pagePublisher)
    {
        $this->site = $site;
        $this->pagePublisher = $pagePublisher;
    }

    /**
     * Publishes all aggregators, taxonomy indexes, sitemaps, and timeline pages.
     *
     * @param Pages $pages
     * @return void
     */
    public function publishAll(Pages $pages): void
    {
        $this->publishSitemaps();
        $this->publishKindIndexes($pages);
        $this->publishTaxonomies($pages);
    }

    /**
     * Generates a sitemap.xml / index page for all active languages.
     *
     * @return void
     */
    public function publishSitemaps(): void
    {
        $defaultLang = $this->site->localization->defaultLang ?? 'en';
        $indexSlugConfig = $this->site->config['index_slug'] ?? 'index';

        foreach ($this->site->localization->lang as $lang) {
            $prettylinks = $this->site->options->prettylinks ?? true;
            if ($prettylinks) {
                $sitemapSlug = ($lang === $defaultLang ? '' : $lang . '/') . $indexSlugConfig . '/';
            } else {
                $sitemapSlug = ($lang === $defaultLang ? '' : $lang . '/') . $indexSlugConfig . '.html';
            }

            $sitemapPage = Page::fromArray([
                'title' => 'Índice',
                'layout' => 'index_page',
                'slug' => $sitemapSlug,
                'date' => time(),
                'content' => '',
                'rawBody' => '',
                'lang' => $lang,
                'kind' => 'generic'
            ]);

            $this->pagePublisher->publish($sitemapPage);
        }
    }

    /**
     * Publishes section and timeline indexes for all configured post kinds.
     *
     * @param Pages $pages
     * @return void
     */
    public function publishKindIndexes(Pages $pages): void
    {
        $pagesByKind = [];
        foreach ($pages as $page) {
            $pagesByKind[$page->kind][] = $page;
        }

        $allKinds = array_unique(array_merge(
            array_keys($this->site->config['kinds'] ?? []),
            array_keys($pagesByKind)
        ));

        foreach ($allKinds as $kind) {
            if (in_array($kind, ['generic', 'page', 'home'], true)) {
                continue;
            }
            $pagesForKind = $pagesByKind[$kind] ?? [];
            if (empty($pagesForKind)) {
                continue;
            }
            $config = $this->site->config['kinds'][$kind] ?? KindHelper::getKindConfig($kind);
            if (isset($config['show_in_menu']) && !$config['show_in_menu']) {
                continue;
            }

            $displayMode = $config['display_mode'] ?? 'default';

            if ($displayMode === 'full_content') {
                $this->compileTimelineIndexes($kind, $pagesForKind);
            } else {
                $this->compileSectionIndexes($kind, $pagesForKind);
            }
        }
    }

    /**
     * Publishes index pages for standard taxonomies (tags and flowerbeds).
     *
     * @param Pages $pages
     * @return void
     */
    public function publishTaxonomies(Pages $pages): void
    {
        $this->compileTaxonomyIndexes('tag', 'tags', $pages);
        $this->compileTaxonomyIndexes('flowerbed', 'flowerbed', $pages);
    }

    /**
     * Compiles the static timeline page from subscribed feeds and hubs.
     *
     * @return void
     */
    public function publishTimelineStaticPage(): void
    {
        $base = $this->site->paths->baseDir;
        $twtxtManager = new TwtxtManager();
        $cacheDir = $base . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'twtxt_cache';

        $timelineEntries = [];
        $mentionEntries = [];

        if (!empty($this->site->twtxt->following)) {
            $timelineEntries = $twtxtManager->fetchTimeline($this->site->twtxt->following, $cacheDir, false);
        }
        if (!empty($this->site->twtxt->hubs)) {
            $mentionEntries = $twtxtManager->fetchHubMentions(
                $this->site->twtxt->hubs,
                $this->site->metadata->fqdn,
                $cacheDir,
                false
            );
        }

        echo "Compiling timeline static page...\n";
        $timelinePage = Page::fromArray([
            'title' => 'Timeline',
            'layout' => 'timeline',
            'slug' => 'timeline/',
            'date' => time(),
            'content' => '',
            'originalcontent' => ''
        ]);

        global $timeline, $mentions;
        $timeline = $timelineEntries;
        $mentions = $mentionEntries;

        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $layoutFile = $base . DIRECTORY_SEPARATOR . $themeDir
            . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'timeline.php';
        if (file_exists($layoutFile) && is_readable($layoutFile)) {
            $this->pagePublisher->publishHtml($timelinePage);
        } else {
            echo "Skipping timeline static page compilation: timeline layout not found.\n";
        }
    }

    /**
     * Loads the theme feed view file if provided by the active theme.
     *
     * @param Pages $pages
     * @return void
     */
    public function loadThemeFeedView(Pages $pages): void
    {
        $base = $this->site->paths->baseDir;
        $site = $this->site;
        // Expose to global scope for view template compatibility
        global $pages, $site;

        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $file = $base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . 'views'
            . DIRECTORY_SEPARATOR . 'feed.php';
        if (file_exists($file) && is_readable($file)) {
            ThemeManager::loadView($file, get_defined_vars());
        }
    }

    /**
     * @param string $targetKind
     * @param Page[] $pages
     * @return void
     */
    public function compileTimelineIndexes(string $targetKind, array $pages): void
    {
        $grouped = [];
        foreach ($pages as $p) {
            if (!empty($p->filepath) && basename($p->filepath) === 'intro.md') {
                continue;
            }

            $lang = $p->lang ?? 'en';
            $date = $p->date;
            $yearMonth = $date->format('Y-m');

            $grouped[$lang][$yearMonth][] = $p;
        }

        foreach ($grouped as $lang => &$months) {
            krsort($months);
            foreach ($months as $yearMonth => &$monthPages) {
                usort($monthPages, function ($a, $b) {
                    $timeA = $a->date->getTimestamp();
                    $timeB = $b->date->getTimestamp();
                    return $timeB <=> $timeA;
                });
            }
            unset($monthPages);
        }
        unset($months);

        $base = $this->site->paths->baseDir;
        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $summaryFile = $base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . 'views'
            . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'summary.php';

        foreach ($grouped as $lang => $months) {
            /** @var Page[] $allPagesForLang */
            $allPagesForLang = [];
            $titleBase = KindHelper::kindLabel($targetKind, $lang);

            foreach ($months as $yearMonth => $monthPages) {
                $monthSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/')
                    . KindHelper::getKindFolder($targetKind, $lang) . '/' . $yearMonth . '/';
                $monthPage = Page::fromArray([
                    'title' => $titleBase . ' - ' . $yearMonth,
                    'layout' => 'index_page',
                    'slug' => $monthSlug,
                    'date' => new DateTime($yearMonth . '-01'),
                    'content' => '',
                    'rawBody' => '',
                    'lang' => $lang,
                    'kind' => $targetKind
                ]);

                $monthContent = '';
                $monthRaw = '';
                foreach ($monthPages as $idx => $p) {
                    if ($idx > 0) {
                        $monthContent .= "\n<hr class=\"divisor-bloco\">\n";
                        $monthRaw .= "\n\n---\n\n";
                    }

                    if (file_exists($summaryFile)) {
                        ob_start();
                        \Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Site::class, $this->site);
                        $site = $this->site;
                        $page = clone $p;
                        $page->relpath = $monthPage->relpath;
                        ThemeManager::loadView($summaryFile, get_defined_vars());
                        $monthContent .= ob_get_clean();
                    } else {
                        $monthContent .= $p->content;
                    }
                    $monthRaw .= $p->rawBody;
                }

                $monthPage->content->content = $monthContent;
                $monthPage->content->rawBody = $monthRaw;

                $allPagesForLang = array_merge($allPagesForLang, $monthPages);

                $this->pagePublisher->publish($monthPage);
            }

            $indexSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/')
                . KindHelper::getKindFolder($targetKind, $lang) . '/';
            $indexPage = Page::fromArray([
                'title' => $titleBase,
                'layout' => 'index_page',
                'slug' => $indexSlug,
                'date' => time(),
                'content' => '',
                'rawBody' => '',
                'lang' => $lang,
                'kind' => $targetKind
            ]);

            $indexContent = '';
            $indexRaw = '';
            foreach ($allPagesForLang as $idx => $p) {
                if ($idx > 0) {
                    $indexContent .= "\n<hr class=\"divisor-bloco\">\n";
                    $indexRaw .= "\n\n---\n\n";
                }

                if (file_exists($summaryFile)) {
                    ob_start();
                    \Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Site::class, $this->site);
                    $site = $this->site;
                    $page = clone $p;
                    $page->relpath = $indexPage->relpath;
                    ThemeManager::loadView($summaryFile, get_defined_vars());
                    $indexContent .= ob_get_clean();
                } else {
                    $indexContent .= $p->content;
                }
                $indexRaw .= $p->rawBody;
            }

            $indexPage->content->content = $indexContent;
            $indexPage->content->rawBody = $indexRaw;

            $this->pagePublisher->publish($indexPage);
        }
    }

    /**
     * @param string $targetKind
     * @param array<int, Page> $pages
     * @return void
     */
    public function compileSectionIndexes(string $targetKind, array $pages): void
    {
        $defaultLang = $this->site->localization->defaultLang;
        $prettylinks = $this->site->options->prettylinks ?? true;

        // Group by language
        $grouped = [];
        $activeLangs = $this->site->localization->lang ?? [$defaultLang];
        foreach ($activeLangs as $l) {
            $grouped[$l] = [];
        }
        foreach ($pages as $p) {
            if (!in_array('draft', $p->metadata->tags)) {
                $grouped[$p->lang ?? $defaultLang][] = $p;
            }
        }

        foreach ($grouped as $lang => $kindPages) {
            usort($kindPages, function ($a, $b) {
                $timeA = $a->date->getTimestamp();
                $timeB = $b->date->getTimestamp();
                return $timeB <=> $timeA;
            });

            $title = KindHelper::kindLabel($targetKind, $lang);

            $kindFolder = KindHelper::getKindFolder($targetKind, $lang);
            $kindSlug = ($lang === $defaultLang ? '' : $lang . '/') . $kindFolder . '/';
            if (!$prettylinks) {
                $kindSlug = ($lang === $defaultLang ? '' : $lang . '/') . $kindFolder . '.html';
            }

            $indexPage = Page::fromArray([
                'title'   => $title,
                'layout'  => 'page',
                'slug'    => $kindSlug,
                'rawBody' => '',
                'content' => '',
                'lang'    => $lang,
                'kind'    => $targetKind
            ]);

            $content = '<ul style="list-style-type: none; padding-left: 0;">';
            foreach ($kindPages as $p) {
                $content .= '<li style="margin-bottom: 1.0em;">';
                $content .= ThemeHelper::renderPostSnippet($indexPage, $p);
                $content .= '</li>';
            }
            $content .= '</ul>';

            $indexPage->content->content = $content;

            $this->pagePublisher->publish($indexPage);
        }
    }

    /**
     * Compiles index pages for a taxonomy (e.g. tags or flowerbeds).
     *
     * @param string $taxonomyName The internal slug (e.g. 'tag', 'flowerbed')
     * @param string $taxonomyKey The metadata key (e.g. 'tags', 'flowerbed')
     * @param iterable<Page> $pages
     * @return void
     */
    public function compileTaxonomyIndexes(string $taxonomyName, string $taxonomyKey, iterable $pages): void
    {
        $grouped = [];
        foreach ($pages as $p) {
            if (($p->filepath && basename($p->filepath) === 'intro.md') || in_array('draft', $p->metadata->tags)) {
                continue;
            }

            if ($taxonomyKey === 'flowerbed' && !in_array($p->kind, ['jardim', 'garden'])) {
                continue;
            }

            $lang = $p->lang ?? 'en';
            $terms = $p->metadata->{$taxonomyKey} ?? [];
            if (!is_array($terms)) {
                $terms = [$terms];
            }

            foreach ($terms as $term) {
                if (empty(trim($term))) {
                    continue;
                }
                $grouped[$lang][$term][] = $p;
            }
        }

        foreach ($grouped as $lang => $terms) {
            uksort($terms, 'strcasecmp');

            $globalContent = "<ul>\n";
            $globalRaw = '';

            foreach ($terms as $term => $termPages) {
                usort($termPages, function ($a, $b) {
                    return $b->date->getTimestamp() <=> $a->date->getTimestamp();
                });

                $termSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/')
                    . $taxonomyName . '/' . TextParser::slugize($term) . '/';

                $count = count($termPages);
                $globalContent .= '<li><a href="/' . $termSlug . '">' . htmlspecialchars($term) . '</a> ('
                    . $count . ')</li>' . "\n";
                $globalRaw .= "=> /" . $termSlug . ' ' . $term . ' (' . $count . ")\n";

                $termTitleBase = Translator::translate(ucfirst($taxonomyName)) . ': ' . $term;

                $termPage = Page::fromArray([
                    'title' => $termTitleBase,
                    'layout' => 'page',
                    'slug' => $termSlug,
                    'date' => time(),
                    'content' => '',
                    'rawBody' => '',
                    'lang' => $lang,
                    'kind' => 'generic'
                ]);

                $termContent = '<ul style="list-style-type: none; padding-left: 0;">' . "\n";
                $termRaw = '';
                foreach ($termPages as $idx => $p) {
                    $termContent .= '<li style="margin-bottom: 1.0em;">' . "\n";
                    $termContent .= ThemeHelper::renderPostSnippet($termPage, $p);
                    $termContent .= '</li>' . "\n";

                    if ($idx > 0) {
                        $termRaw .= "\n\n---\n\n";
                    }
                    $termRaw .= $p->rawBody;
                }
                $termContent .= "</ul>\n";

                $termPage->content->content = $termContent;
                $termPage->content->rawBody = $termRaw;

                $this->pagePublisher->publish($termPage);
            }

            $globalContent .= "</ul>\n";

            $globalTitleBase = Translator::translate(ucfirst($taxonomyName) . 's');
            $globalSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/')
                . $taxonomyName . '/';
            $globalPage = Page::fromArray([
                'title' => $globalTitleBase,
                'layout' => 'page',
                'slug' => $globalSlug,
                'date' => time(),
                'content' => $globalContent,
                'rawBody' => $globalRaw,
                'lang' => $lang,
                'kind' => 'generic'
            ]);

            $this->pagePublisher->publish($globalPage);
        }
    }
}
