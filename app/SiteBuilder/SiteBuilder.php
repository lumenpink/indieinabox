<?php

declare(strict_types=1);

namespace Indieinabox\SiteBuilder;

use Indieinabox\Markdown\ParserInterface;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\AssetPublisher;
use Indieinabox\SiteBuilder\ContentScanner;
use Indieinabox\SiteBuilder\FeedPublisher;
use Indieinabox\SiteBuilder\IndexPublisher;
use Indieinabox\SiteBuilder\PagePublisher;
use Indieinabox\SiteBuilder\TranslationVirtualizer;

/**
 * Class SiteBuilder
 * 
 * Orchestrates the static site generation process. It coordinates scanning,
 * translation virtualization, content rendering, feed generation, and asset publishing.
 */
class SiteBuilder
{
    /**
     * @var \Indieinabox\Site\Site
     */
    private Site $site;
    /**
     * @var \Indieinabox\Page\Pages
     */
    private Pages $pages;
    /**
     * @var \Indieinabox\Markdown\ParserInterface
     */
    private ParserInterface $parser;
    /**
     * @var \Indieinabox\SiteBuilder\ContentScanner
     */
    private ContentScanner $contentScanner;
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
     * @param \Indieinabox\Site\Site $site The site configuration and environment settings.
     * @param \Indieinabox\Page\Pages|null $pages An optional collection of parsed pages.
     * @param \Indieinabox\Markdown\ParserInterface|null $parser An optional markdown parser implementation.
     * @param \Indieinabox\SiteBuilder\AssetPublisher|null $assetPublisher An optional asset publisher.
     * @param \Indieinabox\SiteBuilder\FeedPublisher|null $feedPublisher An optional feed publisher.
     * @param \Indieinabox\SiteBuilder\PagePublisher|null $pagePublisher An optional page publisher.
     * @param \Indieinabox\SiteBuilder\TranslationVirtualizer|null $translationVirtualizer An optional translation virtualizer.
     * @param \Indieinabox\SiteBuilder\IndexPublisher|null $indexPublisher An optional index publisher.
     * @param \Indieinabox\SiteBuilder\ContentScanner|null $contentScanner An optional content scanner.
     */
    public function __construct(
        Site $site,
        ?Pages $pages = null,
        ?ParserInterface $parser = null,
        ?AssetPublisher $assetPublisher = null,
        ?FeedPublisher $feedPublisher = null,
        ?PagePublisher $pagePublisher = null,
        ?TranslationVirtualizer $translationVirtualizer = null,
        ?IndexPublisher $indexPublisher = null,
        ?ContentScanner $contentScanner = null
    ) {
        $this->site = $site;
        $this->pages = $pages ?? new Pages();
        $this->contentScanner = $contentScanner ?? new ContentScanner($this->site, $parser);
        $this->parser = $this->contentScanner->getParser();
        $this->assetPublisher = $assetPublisher ?? new AssetPublisher($this->site);
        $this->feedPublisher = $feedPublisher ?? new FeedPublisher($this->site);
        $this->pagePublisher = $pagePublisher ?? new PagePublisher($this->site, $this->pages);
        $this->translationVirtualizer = $translationVirtualizer ?? new TranslationVirtualizer($this->site);
        $this->indexPublisher = $indexPublisher ?? new IndexPublisher($this->site, $this->pagePublisher);
    }

    /**
     * Retrieves the collection of processed pages.
     *
     * @return \Indieinabox\Page\Pages The pages collection.
     */
    public function getPages(): Pages
    {
        return $this->pages;
    }

    /**
     * Retrieves the markdown parser implementation.
     *
     * @return \Indieinabox\Markdown\ParserInterface
     */
    public function getParser(): ParserInterface
    {
        return $this->parser;
    }

    /**
     * Retrieves the content scanner instance.
     *
     * @return \Indieinabox\SiteBuilder\ContentScanner
     */
    public function getContentScanner(): ContentScanner
    {
        return $this->contentScanner;
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

        // Scan content & render
        $s1 = microtime(true);
        $this->scan($this->site->paths->getContentPath());
        $this->ensureMandatoryHomepage();
        $this->translationVirtualizer->virtualize($this->pages);
        $this->contentScanner->renderRawBodies($this->pages);
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
     * Recursively scans a directory for markdown content files.
     * Delegates to ContentScanner.
     *
     * @param string $dir The directory path to scan.
     * @return void
     */
    public function scan(string $dir): void
    {
        $this->contentScanner->scan($dir, $this->pages);
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
     * Delegates to ContentScanner.
     *
     * @return void
     */
    public function ensureMandatoryHomepage(): void
    {
        $this->contentScanner->ensureMandatoryHomepage($this->pages);
    }
}
