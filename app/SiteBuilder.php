<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Markdown\FileProcessor;
use Indieinabox\Markdown\ContentProcessor;
use Indieinabox\Markdown\LanguageProcessor;
use Indieinabox\Translations\UrlTranslations;
use Indieinabox\SiteBuilder\AssetPublisher;
use Indieinabox\SiteBuilder\FeedPublisher;
use Indieinabox\SiteBuilder\PagePublisher;

/**
 * Class SiteBuilder
 * 
 * Orchestrates the static site generation process. It scans the content directory,
 * virtualizes missing translations, processes markdown into HTML/Gemtext/Gophermap,
 * and compiles feeds and assets into the output directory.
 */
class SiteBuilder
{
    /**
     * @var \Indieinabox\Site
     */
    private Site $site;
    /**
     * @var \Indieinabox\Pages
     */
    private Pages $pages;
    /**
     * @var \Indieinabox\ParserInterface
     */
    private ParserInterface $parser;
    /**
     * @var \Indieinabox\SiteBuilder\AssetPublisher
     */
    private AssetPublisher $assetPublisher;
    /**
     * @var \Indieinabox\SiteBuilder\FeedPublisher
     */
    private FeedPublisher $feedPublisher;
    /**
     * @var \Indieinabox\SiteBuilder\PagePublisher
     */
    private PagePublisher $pagePublisher;

    /**
     * SiteBuilder constructor.
     *
     * @param \Indieinabox\Site $site The site configuration and environment settings.
     * @param \Indieinabox\Pages|null $pages An optional collection of parsed pages.
     * @param \Indieinabox\ParserInterface|null $parser An optional markdown parser implementation.
     * @param \Indieinabox\SiteBuilder\AssetPublisher|null $assetPublisher An optional asset publisher.
     * @param \Indieinabox\SiteBuilder\FeedPublisher|null $feedPublisher An optional feed publisher.
     * @param \Indieinabox\SiteBuilder\PagePublisher|null $pagePublisher An optional page publisher.
     */
    public function __construct(
        Site $site,
        ?Pages $pages = null,
        ?ParserInterface $parser = null,
        ?AssetPublisher $assetPublisher = null,
        ?FeedPublisher $feedPublisher = null,
        ?PagePublisher $pagePublisher = null
    ) {
        $this->site = $site;
        $this->pages = $pages ?? new Pages();
        $this->assetPublisher = $assetPublisher ?? new AssetPublisher($this->site);
        $this->feedPublisher = $feedPublisher ?? new FeedPublisher($this->site);
        $this->pagePublisher = $pagePublisher ?? new PagePublisher($this->site, $this->pages);

        if ($parser !== null) {
            $this->parser = $parser;
        } else {
            $base = $this->site->paths->baseDir;
            global $urltranslations;

            $fileProcessor     = new FileProcessor($this->site, $base);
            $contentProcessor  = new ContentProcessor();
            $urlTranslationsObj   = new UrlTranslations($urltranslations ?? []);
            $languageProcessor = new LanguageProcessor($this->site, $urlTranslationsObj);

            $this->parser = new MarkdownParser(
                $fileProcessor,
                $contentProcessor,
                $languageProcessor,
                $this->site
            );
        }
    }

    /**
     * Retrieves the collection of processed pages.
     *
     * @return \Indieinabox\Pages The pages collection.
     */
    public function getPages(): Pages
    {
        return $this->pages;
    }

    /**
     * Retrieves the asset publisher instance.
     *
     * @return \Indieinabox\SiteBuilder\AssetPublisher
     */
    public function getAssetPublisher(): AssetPublisher
    {
        return $this->assetPublisher;
    }

    /**
     * Retrieves the feed publisher instance.
     *
     * @return \Indieinabox\SiteBuilder\FeedPublisher
     */
    public function getFeedPublisher(): FeedPublisher
    {
        return $this->feedPublisher;
    }

    /**
     * Retrieves the page publisher instance.
     *
     * @return \Indieinabox\SiteBuilder\PagePublisher
     */
    public function getPagePublisher(): PagePublisher
    {
        return $this->pagePublisher;
    }

    /**
     * Stores absolute paths of all generated files during the build process
     * for Garbage Collection.
     * @var string[]
     */
    public static array $manifest = [];

    /**
     * Adds a file path to the manifest array.
     * 
     * @param string $path
     * @return void
     */
    public static function addManifest(string $path): void
    {
        self::$manifest[$path] = true;
    }

    /**
     * Executes the main build pipeline.
     * 
     * Cleans the output directory, scans content files, handles translation virtualization,
     * and triggers generation of HTML, feeds, and static assets.
     */
    public function build(): void
    {
        $base = $this->site->paths->baseDir;
        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $timings = [];
        $t_start = microtime(true);

        // Scan content
        $s1 = microtime(true);
        $this->scan($this->site->paths->getContentPath());
        $this->ensureMandatoryHomepage();
        $this->virtualizeMissingLanguages();

        // Pass 2: Render Markdown to HTML now that all pages are scanned
        global $pages, $site;
        $pages = $this->pages;
        $site = $this->site;

        $contentProcessor = new ContentProcessor();
        foreach ($this->pages as $page) {
            if (isset($page->rawBody) && $page->rawBody !== '') {
                $renderedContent = $contentProcessor->processContent($page->rawBody, $page);
                $page->content->content = trim($renderedContent, " \n\r\t");
            }
        }

        $s2 = microtime(true);
        $timings['Scan + Virtualize'] = ($s2 - $s1) * 1000;

        // Generate files
        if (isset($this->site->options->skipPages) && $this->site->options->skipPages) {
            echo "Skipping page generation\n";
        } else {
            $this->generateHTMLFiles();
        }
        $s3 = microtime(true);
        $timings['Generate HTML/GMI/Gopher'] = ($s3 - $s2) * 1000;
        
        // Generate Feeds
        $this->feedPublisher->publishFeeds($this->pages);
        $this->compileTimelineStaticPage();
        $this->loadThemeFeedView();
        $s4 = microtime(true);
        $timings['Generate Feeds'] = ($s4 - $s3) * 1000;

        // Copy assets
        $this->assetPublisher->publishViewAssets($base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views");
        $s5 = microtime(true);
        $timings['Copy Assets'] = ($s5 - $s4) * 1000;

        // Copy Media
        if (isset($this->site->options->skipMedia) && $this->site->options->skipMedia) {
            echo "Skipping media files\n";
        } else {
            $this->assetPublisher->publishMedia();
        }
        $s6 = microtime(true);
        $timings['Copy Media'] = ($s6 - $s5) * 1000;

        // Copy static files
        if ($this->site->options->skipStatic) {
            echo "Skipping static files\n";
        } else {
            $this->assetPublisher->publishStaticFiles($base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "static");
        }
        $s7 = microtime(true);
        $timings['Copy Static Files'] = ($s7 - $s6) * 1000;

        $this->assetPublisher->garbageCollect(self::$manifest);
        $s8 = microtime(true);
        $timings['Garbage Collect'] = ($s8 - $s7) * 1000;

        $totalTime = ($s8 - $t_start) * 1000;

        // Output summary table
        echo "\n+----------------------------------+-----------------+\n";
        echo "| Task                             | Time (ms)       |\n";
        echo "+----------------------------------+-----------------+\n";
        foreach ($timings as $task => $time) {
            printf("| %-32s | %15.2f |\n", $task, $time);
        }
        echo "+----------------------------------+-----------------+\n";
        printf("| %-32s | %15.2f |\n", 'TOTAL BUILD TIME', $totalTime);
        echo "+----------------------------------+-----------------+\n";
    }
    /**
     * Generates pseudo-translated pages for missing languages to maintain parity.
     * Uses configured rules (e.g., full parity, from-main-only) and translates 
     * missing slugs according to URL translation mappings.
     *
     * @return void
     */
    private function virtualizeMissingLanguages(): void
    {
        $langs = $this->site->localization->lang;
        if (count($langs) <= 1) {
            return;
        }

        $defaultLang = $this->site->localization->defaultLang ?? 'en';
        $prettylinks = $this->site->options->prettylinks ?? true;
        
        $parity = $this->site->options->translation_parity ?? 'full';
        if ($parity === 'disabled') {
            return;
        }
        $autoVirtualize = $this->site->options->translation_auto ?? 'pseudo';

        global $urltranslations;
        $urlTranslationsArr = $urltranslations ?? [];
        $reverseTranslations = [];
        foreach ($urlTranslationsArr as $defaultNick => $translations) {
            foreach ($translations as $l => $translatedNick) {
                $reverseTranslations[$l][$translatedNick] = $defaultNick;
            }
        }

        $existing = [];
        $pagesToProcess = [];
        foreach ($this->pages as $page) {
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
                        throw new \RuntimeException(
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

                    $kindFolder = Helper::getKindFolder($cloned->kind, $targetLang);
                    $sourceKindFolder = Helper::getKindFolder($page->kind, $sourceLang);
                    
                    if (in_array($sourceKindFolder, ['page', 'generic', 'home'], true)) $sourceKindFolder = '';
                    if (in_array($kindFolder, ['page', 'generic', 'home'], true)) $kindFolder = '';
                    
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
                    if ($prettylinks && !str_ends_with($cloned->slug, '.html') && $cloned->slug !== '' && $cloned->slug !== 'index.html') {
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

                    $this->pages->add($cloned);
                }
            }
        }
    }

    /**
     * Applies a pseudo-translation prefix to a page's title or content.
     * Used visually to flag that a page was automatically virtualized.
     *
     * @param \Indieinabox\Page $page The page to translate in place.
     * @param string $targetLang The target language code used as the prefix.
     * @return void
     */
    public function pseudoTranslate(\Indieinabox\Page $page, string $targetLang): void
    {
        $prefix = '[' . strtoupper($targetLang) . '] ';
        $hasTitle = !empty($page->title)
            && $page->title !== 'Untitled'
            && $page->title !== 'untitled';

        $kindConfig = \Indieinabox\Helper::getKindConfig($page->kind);
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

    /**
     * Recursively scans a directory for markdown content files.
     * Parses valid markdown files into Page objects and adds them to the collection.
     * Skips system directories (e.g., app, vendor, output dirs).
     *
     * @param string $dir The directory path to scan.
     * @return void
     */
    public function scan(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if (
                $entry !== "."
                && $entry !== ".."
                && substr($entry, 0, 1) !== "_"
                && substr($entry, 0, 1) !== "."
            ) {
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($path)) {
                    if ($entry === 'intro.md') {
                        continue;
                    }
                    $page = $this->parser->parse($path);
                    if ($page) {
                        $this->pages->add($page);
                    }
                } elseif (is_dir($path)) {
                    $baseDir = rtrim($this->site->paths->baseDir ?? '', DIRECTORY_SEPARATOR);
                    $themeDir = $this->site->paths->themeDir ?? 'theme';
                    $ignoredDirs = [
                        $baseDir . DIRECTORY_SEPARATOR . "app",
                        $baseDir . DIRECTORY_SEPARATOR . "bootstrap",
                        $baseDir . DIRECTORY_SEPARATOR . "vendor",
                        $baseDir . DIRECTORY_SEPARATOR . "resources",
                        $baseDir . DIRECTORY_SEPARATOR . "theme",
                        $baseDir . DIRECTORY_SEPARATOR . "data",
                        $baseDir . DIRECTORY_SEPARATOR . $themeDir,
                        $baseDir . DIRECTORY_SEPARATOR . $this->site->paths->outputDirHtml,
                        $baseDir . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGemini,
                        $baseDir . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGopher,
                        $baseDir . DIRECTORY_SEPARATOR . $this->site->paths->outputDirMedia,
                    ];
                    $skip = false;
                    foreach ($ignoredDirs as $ignored) {
                        if ($path === $ignored || strpos($path, $ignored . DIRECTORY_SEPARATOR) === 0) {
                            $skip = true;
                            break;
                        }
                    }
                    if (!$skip) {
                        $this->scan($path);
                    }
                }
            }
        }
    }

    /**
     * Iterates over all parsed pages and triggers the generation of HTML, 
     * Gemini, and Gopher files for each. Also generates sitemaps and indexes.
     *
     * @return void
     */
    public function generateHTMLFiles(): void
    {
        $pagesByKind = [];
        foreach ($this->pages as $page) {
            $pagesByKind[$page->kind][] = $page;
            $this->pagePublisher->publish($page);
        }

        // Generate Sitemap
        $this->compileSitemap();

        $allKinds = array_unique(array_merge(
            array_keys($this->site->config['kinds'] ?? []),
            array_keys($pagesByKind)
        ));

        foreach ($allKinds as $kind) {
            if (in_array($kind, ['generic', 'page', 'home'])) {
                continue;
            }
            $pagesForKind = $pagesByKind[$kind] ?? [];
            if (empty($pagesForKind)) {
                continue;
            }
            $config = $this->site->config['kinds'][$kind] ?? Helper::getKindConfig($kind);
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
        
        $this->compileTaxonomyIndexes('tag', 'tags', $this->pages);
        $this->compileTaxonomyIndexes('flowerbed', 'flowerbed', $this->pages);
    }

    /**
     * Loads the theme feed view file if provided by the active theme.
     */
    private function loadThemeFeedView(): void
    {
        $base = $this->site->paths->baseDir;
        $site = $this->site;
        $pages = $this->pages;
        // Expose to global scope for view template compatibility
        global $pages, $site;

        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $file = $base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views"
            . DIRECTORY_SEPARATOR . "feed" . ".php";
        if (file_exists($file) && is_readable($file)) {
            ThemeManager::loadView($file, get_defined_vars());
        }
    }

    /**
     * Compiles the static timeline page from subscribed feeds and hubs.
     *
     * @return void
     */
    private function compileTimelineStaticPage(): void
    {
        $base = $this->site->paths->baseDir;
        $twtxtManager = new \Indieinabox\Twtxt\TwtxtManager();
        $cacheDir = $base . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'twtxt_cache';

        $timelineEntries = [];
        $mentionEntries = [];

        if (!empty($this->site->twtxt->following)) {
            $timelineEntries = $twtxtManager->fetchTimeline($this->site->twtxt->following, $cacheDir, false);
        }
        if (!empty($this->site->twtxt->hubs)) {
            $mentionEntries = $twtxtManager->fetchHubMentions($this->site->twtxt->hubs, $this->site->metadata->fqdn, $cacheDir, false);
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
     * @param \Indieinabox\Page[] $pages
     */
    private function compileTimelineIndexes(string $targetKind, array $pages): void
    {
        $grouped = [];
        foreach ($pages as $p) {
            if (basename($p->filepath) === 'intro.md') {
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
        $summaryFile = $base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views"
            . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "summary.php";

        foreach ($grouped as $lang => $months) {
            /** @var \Indieinabox\Page[] $allPagesForLang */
            $allPagesForLang = [];
            $titleBase = \Indieinabox\Helper::kindLabel($targetKind, $lang);

            foreach ($months as $yearMonth => $monthPages) {
                $monthSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/')
                    . Helper::getKindFolder($targetKind, $lang) . '/' . $yearMonth . '/';
                $monthPage = Page::fromArray([
                    'title' => $titleBase . " - " . $yearMonth,
                    'layout' => 'index_page',
                    'slug' => $monthSlug,
                    'date' => new \DateTime($yearMonth . '-01'),
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
                        global $site;
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
                . Helper::getKindFolder($targetKind, $lang) . '/';
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
                    global $site;
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
     * Generates a sitemap.xml file encompassing all non-draft pages.
     * Creates standard sitemaps for the HTML site to assist search engine indexing.
     *
     * @return void
     */
    private function compileSitemap(): void
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
                'title' => "Índice",
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
     * @param string $targetKind
     * @param array<int, Page> $pages
     */
    private function compileSectionIndexes(string $targetKind, array $pages): void
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

                $title = \Indieinabox\Helper::kindLabel($targetKind, $lang);
                $displayMode = \Indieinabox\Helper::getKindConfig($targetKind)['display_mode'] ?? 'default';

                $kindFolder = Helper::getKindFolder($targetKind, $lang);
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
                    $content .= \Indieinabox\Theme\ThemeHelper::renderPostSnippet($indexPage, $p);
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
     * @param iterable $pages
     */
    private function compileTaxonomyIndexes(string $taxonomyName, string $taxonomyKey, iterable $pages): void
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
                if (empty(trim($term))) continue;
                $grouped[$lang][$term][] = $p;
            }
        }

        $base = $this->site->paths->baseDir;
        $themeDir = $this->site->paths->themeDir ?? 'theme';
        $summaryFile = $base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views"
            . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "summary.php";

        foreach ($grouped as $lang => $terms) {
            uksort($terms, 'strcasecmp');
            
            $globalContent = "<ul>\n";
            $globalRaw = "";

            foreach ($terms as $term => $termPages) {
                usort($termPages, function ($a, $b) {
                    return $b->date->getTimestamp() <=> $a->date->getTimestamp();
                });

                $termSlug = ($lang === $this->site->localization->defaultLang ? '' : $lang . '/')
                    . $taxonomyName . '/' . \Indieinabox\Helper::slugize($term) . '/';
                
                $count = count($termPages);
                $globalContent .= '<li><a href="/' . $termSlug . '">' . htmlspecialchars($term) . '</a> (' . $count . ')</li>' . "\n";
                $globalRaw .= "=> /" . $termSlug . " " . $term . " (" . $count . ")\n";
                
                $termTitleBase = \Indieinabox\Helper::translate(ucfirst($taxonomyName)) . ': ' . $term;
                
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
                    $termContent .= \Indieinabox\Theme\ThemeHelper::renderPostSnippet($termPage, $p);
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
            
            $globalTitleBase = \Indieinabox\Helper::translate(ucfirst($taxonomyName) . 's');
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
    
    /**
     * Ensures a mandatory homepage (index.html) exists in the output.
     * If one was not provided in the content directory, it creates a generic fallback.
     *
     * @return void
     */
    private function ensureMandatoryHomepage(): void
    {
        $langs = $this->site->localization->lang ?? ['en'];
        $defaultLang = $this->site->localization->defaultLang ?? 'en';
        
        foreach ($langs as $lang) {
            $expectedSlug = ($lang === $defaultLang) ? '/' : $lang . '/';
            $found = false;
            foreach ($this->pages as $p) {
                if ($p->slug === $expectedSlug || rtrim($p->slug, '/') === rtrim($expectedSlug, '/')) {
                    $found = true;
                    // Force the layout to home just in case
                    $p->layout = 'home';
                    break;
                }
            }
            if (!$found) {
                $page = Page::fromArray([
                    'slug' => $expectedSlug,
                    'nick' => 'index',
                    'kind' => 'generic',
                    'title' => $this->site->metadata->title ?? 'Home',
                    'lang' => $lang,
                    'layout' => 'home',
                    'content' => '',
                    'relpath' => ($lang === $defaultLang) ? '' : '../'
                ]);
                $this->pages->add($page);
            }
        }
    }
}
