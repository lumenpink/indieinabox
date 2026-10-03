<?php

declare(strict_types=1);

namespace Indieinabox\SiteBuilder;

use DateTime;
use Indieinabox\Core\Container;
use Indieinabox\Localization\Translator;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Site;
use Indieinabox\Support\TextParser;
use Indieinabox\Taxonomy\Contracts\TaxonomyServiceInterface;
use Indieinabox\Taxonomy\TaxonomyService;
use Indieinabox\Theme\ThemeHelper;
use Indieinabox\Theme\ThemeManager;
use Indieinabox\Twtxt\TwtxtManager;

/**
 * Publishes index pages: section indexes, timeline indexes, taxonomies, and sitemaps.
 */
class IndexPublisher
{
    private Site $site;
    private PagePublisher $pagePublisher;
    private TaxonomyServiceInterface $taxonomyService;

    public function __construct(
        Site $site,
        PagePublisher $pagePublisher,
        ?TaxonomyServiceInterface $taxonomyService = null
    ) {
        $this->site = $site;
        $this->pagePublisher = $pagePublisher;

        $container = class_exists(Container::class) ? Container::getInstance() : null;
        if ($taxonomyService !== null) {
            $this->taxonomyService = $taxonomyService;
        } elseif ($container && $container->has(TaxonomyServiceInterface::class)) {
            $this->taxonomyService = $container->get(TaxonomyServiceInterface::class);
        } else {
            $this->taxonomyService = new TaxonomyService($this->site);
        }
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
        $this->publishSearchIndex($pages);
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
                $sitemapSlug = ($lang === $defaultLang ? '' : $lang . '/') . ($indexSlugConfig === 'index' ? 'sitemap' : $indexSlugConfig) . '.html';
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
            $config = $this->site->config['kinds'][$kind] ?? $this->taxonomyService->getKindConfig($kind);
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

        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $layoutFile = $base . DIRECTORY_SEPARATOR . $themeDir
            . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'timeline.php';
        if (file_exists($layoutFile) && is_readable($layoutFile)) {
            $this->pagePublisher->publishHtml($timelinePage, [
                'timeline' => $timelineEntries,
                'mentions' => $mentionEntries,
            ]);
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
            $_ = $lang; // psalm: key used in second loop
            krsort($months);
            foreach ($months as $yearMonth => &$monthPages) {
                $_ = $yearMonth; // psalm: key used in second loop
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
            $titleBase = $this->taxonomyService->getKindLabel($targetKind, $lang);

            foreach ($months as $yearMonth => $monthPages) {
                $monthSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/')
                    . $this->taxonomyService->getKindFolder($targetKind, $lang) . '/' . $yearMonth . '/';
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
                        \Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Site\Site::class, $this->site);
                        $page = clone $p;
                        $page->relpath = $monthPage->relpath;
                        ThemeManager::loadView($summaryFile, get_defined_vars());
                        $monthContent .= ob_get_clean();
                    } else {
                        $monthContent .= (string) $p->content;
                    }
                    $monthRaw .= $p->rawBody;
                }

                $monthPage->content->content = $monthContent;
                $monthPage->content->rawBody = $monthRaw;

                $allPagesForLang = array_merge($allPagesForLang, $monthPages);

                $this->pagePublisher->publish($monthPage);
            }

            $indexSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/')
                . $this->taxonomyService->getKindFolder($targetKind, $lang) . '/';
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
                    \Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Site\Site::class, $this->site);
                    $site = $this->site;
                    $page = clone $p;
                    $page->relpath = $indexPage->relpath;
                    ThemeManager::loadView($summaryFile, get_defined_vars());
                    $indexContent .= ob_get_clean();
                } else {
                    $indexContent .= (string) $p->content;
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

            $title = $this->taxonomyService->getKindLabel($targetKind, $lang);

            $kindFolder = $this->taxonomyService->getKindFolder($targetKind, $lang);
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

            // Exclude default placeholder
            $terms = array_values(array_filter($terms, function ($t) {
                return is_string($t) && trim($t) !== '' && $t !== 'No Tag' && $t !== 'No Category';
            }));

            // Auto-extract hashtags from post body if indexing tags
            if ($taxonomyKey === 'tags') {
                $rawBody = (string) ($p->rawBody ?? ($p->content->rawBody ?? ''));
                if ($rawBody === '' && isset($p->content->content)) {
                    $rawBody = (string) $p->content->content;
                }
                $extracted = TextParser::extractHashtags($rawBody);
                if (!empty($extracted)) {
                    $terms = array_values(array_unique(array_merge($terms, $extracted)));
                }
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

                // If singular taxonomy (e.g. tag), also publish plural alias (e.g. tags/<slug>/)
                if ($taxonomyName === 'tag') {
                    $termSlugPlural = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/')
                        . 'tags/' . TextParser::slugize($term) . '/';
                    $termPagePlural = clone $termPage;
                    $termPagePlural->slug = $termSlugPlural;
                    $this->pagePublisher->publish($termPagePlural);
                }
            }

            $globalContent .= "</ul>\n";

            $globalTitleBase = Translator::translate(ucfirst($taxonomyName) . 's');
            $pluralTaxonomy = ($taxonomyName === 'tag') ? 'tags' : ($taxonomyName . 's');
            $globalSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/')
                . $pluralTaxonomy . '/';

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

            // Also publish singular alias (e.g. /tag/) if different from plural
            if ($pluralTaxonomy !== $taxonomyName) {
                $singularSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/')
                    . $taxonomyName . '/';
                $singularPage = clone $globalPage;
                $singularPage->slug = $singularSlug;
                $this->pagePublisher->publish($singularPage);
            }
        }
    }

    /**
     * Publishes static search.json index files for client-side search widgets.
     *
     * @param Pages $pages
     * @return void
     */
    public function publishSearchIndex(Pages $pages): void
    {
        $baseDir = rtrim($this->site->paths->baseDir ?? '', DIRECTORY_SEPARATOR);
        $outputHtml = $this->site->paths->outputDirHtml ?? 'public_html';
        $targetDir = $baseDir . DIRECTORY_SEPARATOR . $outputHtml;

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $allEntries = [];
        $entriesByLang = [];

        foreach ($pages as $p) {
            if (($p->filepath && basename($p->filepath) === 'intro.md') || in_array('draft', $p->metadata->tags ?? [])) {
                continue;
            }
            if (in_array($p->kind, ['generic', 'home', 'page'], true) && ($p->slug === '/' || $p->slug === 'index/' || $p->slug === '')) {
                continue;
            }

            $lang = (string) ($p->lang ?? ($this->site->localization->defaultLang ?? 'en'));
            $title = (string) ($p->metadata->title ?? 'Untitled');
            $url = '/' . ltrim((string) $p->slug, '/');

            // Extract tags
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

            // Summary
            $cleanText = strip_tags($rawBody);
            $cleanText = preg_replace('/\s+/', ' ', $cleanText);
            $cleanText = trim((string) $cleanText);
            $summary = mb_substr($cleanText, 0, 180);
            if (mb_strlen($cleanText) > 180) {
                $summary .= '...';
            }

            $dateIso = $p->date instanceof \DateTimeInterface ? $p->date->format('c') : date('c');

            $entry = [
                'title' => $title,
                'url' => $url,
                'slug' => (string) $p->slug,
                'kind' => (string) $p->kind,
                'lang' => $lang,
                'date' => $dateIso,
                'tags' => $tags,
                'summary' => $summary,
            ];

            $allEntries[] = $entry;
            $entriesByLang[$lang][] = $entry;
        }

        // Global search.json
        $globalPath = $targetDir . DIRECTORY_SEPARATOR . 'search.json';
        file_put_contents($globalPath, (string) json_encode($allEntries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        SiteBuilder::addManifest($globalPath);

        // Language-specific search.json
        foreach ($entriesByLang as $lang => $entries) {
            $langDir = $targetDir . DIRECTORY_SEPARATOR . $lang;
            if (!is_dir($langDir)) {
                @mkdir($langDir, 0755, true);
            }
            $langPath = $langDir . DIRECTORY_SEPARATOR . 'search.json';
            file_put_contents($langPath, (string) json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            SiteBuilder::addManifest($langPath);
        }

        // Synchronize SQLite FTS5 search index if database is connected
        if (\Indieinabox\Core\Database::isConnected()) {
            try {
                $searchService = new \Indieinabox\Search\SearchService(null, $this->site);
                $searchService->indexAllPages($pages);
            } catch (\Throwable $e) {
                // Non-fatal if FTS5 indexing fails during static build
            }
        }
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
