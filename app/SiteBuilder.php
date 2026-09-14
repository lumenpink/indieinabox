<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Markdown\FileProcessor;
use Indieinabox\Markdown\ContentProcessor;
use Indieinabox\Markdown\LanguageProcessor;
use Indieinabox\Translations\UrlTranslations;
use Indieinabox\SiteBuilder\AssetPublisher;
use Indieinabox\SiteBuilder\FeedPublisher;
use Indieinabox\SiteBuilder\IndexPublisher;
use Indieinabox\SiteBuilder\PagePublisher;
use Indieinabox\SiteBuilder\TranslationVirtualizer;

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
     * @var \Indieinabox\SiteBuilder\TranslationVirtualizer
     */
    private TranslationVirtualizer $translationVirtualizer;
    /**
     * @var \Indieinabox\SiteBuilder\IndexPublisher
     */
    private IndexPublisher $indexPublisher;

    /**
     * SiteBuilder constructor.
     *
     * @param \Indieinabox\Site $site The site configuration and environment settings.
     * @param \Indieinabox\Pages|null $pages An optional collection of parsed pages.
     * @param \Indieinabox\ParserInterface|null $parser An optional markdown parser implementation.
     * @param \Indieinabox\SiteBuilder\AssetPublisher|null $assetPublisher An optional asset publisher.
     * @param \Indieinabox\SiteBuilder\FeedPublisher|null $feedPublisher An optional feed publisher.
     * @param \Indieinabox\SiteBuilder\PagePublisher|null $pagePublisher An optional page publisher.
     * @param \Indieinabox\SiteBuilder\TranslationVirtualizer|null $translationVirtualizer An optional translation virtualizer.
     * @param \Indieinabox\SiteBuilder\IndexPublisher|null $indexPublisher An optional index publisher.
     */
    public function __construct(
        Site $site,
        ?Pages $pages = null,
        ?ParserInterface $parser = null,
        ?AssetPublisher $assetPublisher = null,
        ?FeedPublisher $feedPublisher = null,
        ?PagePublisher $pagePublisher = null,
        ?TranslationVirtualizer $translationVirtualizer = null,
        ?IndexPublisher $indexPublisher = null
    ) {
        $this->site = $site;
        $this->pages = $pages ?? new Pages();
        $this->assetPublisher = $assetPublisher ?? new AssetPublisher($this->site);
        $this->feedPublisher = $feedPublisher ?? new FeedPublisher($this->site);
        $this->pagePublisher = $pagePublisher ?? new PagePublisher($this->site, $this->pages);
        $this->translationVirtualizer = $translationVirtualizer ?? new TranslationVirtualizer($this->site);
        $this->indexPublisher = $indexPublisher ?? new IndexPublisher($this->site, $this->pagePublisher);

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
     * Retrieves the translation virtualizer instance.
     *
     * @return \Indieinabox\SiteBuilder\TranslationVirtualizer
     */
    public function getTranslationVirtualizer(): TranslationVirtualizer
    {
        return $this->translationVirtualizer;
    }

    /**
     * Retrieves the index publisher instance.
     *
     * @return \Indieinabox\SiteBuilder\IndexPublisher
     */
    public function getIndexPublisher(): IndexPublisher
    {
        return $this->indexPublisher;
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
        $this->translationVirtualizer->virtualize($this->pages);

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
        $this->indexPublisher->publishTimelineStaticPage();
        $this->indexPublisher->loadThemeFeedView($this->pages);
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
     * Delegates to TranslationVirtualizer.
     *
     * @return void
     */
    public function virtualizeMissingLanguages(): void
    {
        $this->translationVirtualizer->virtualize($this->pages);
    }

    /**
     * Applies a pseudo-translation prefix to a page's title or content.
     * Delegates to TranslationVirtualizer.
     *
     * @param \Indieinabox\Page $page
     * @param string $targetLang
     * @return void
     */
    public function pseudoTranslate(\Indieinabox\Page $page, string $targetLang): void
    {
        $this->translationVirtualizer->pseudoTranslate($page, $targetLang);
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
        foreach ($this->pages as $page) {
            $this->pagePublisher->publish($page);
        }

        $this->indexPublisher->publishAll($this->pages);
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
