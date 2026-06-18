<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Markdown\FileProcessor;
use Indieinabox\Markdown\ContentProcessor;
use Indieinabox\Markdown\LanguageProcessor;
use Indieinabox\Translations\UrlTranslations;
use Indieinabox\Markdown\ASTParser;
use Indieinabox\Markdown\GemtextRenderer;
use Indieinabox\Markdown\GophermapRenderer;

class SiteBuilder
{
    private Site $site;
    private Pages $pages;
    private MarkdownParser $parser;

    public function __construct(Site $site, ?Pages $pages = null)
    {
        $this->site = $site;
        $this->pages = $pages ?? new Pages();

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

    public function getPages(): Pages
    {
        return $this->pages;
    }

    public function build(): void
    {
        $base = $this->site->paths->baseDir;

        // Clean output directory
        Helper::recursive_rmdir($base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir);

        // Scan content
        $this->scan($base . DIRECTORY_SEPARATOR . $this->site->paths->contentDir);

        // Generate files
        $this->generateHTMLFiles();
        $this->generateTwtxt();
        $this->generateFeed();

        // Copy assets
        $this->copyAssets($base . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "views");

        // Copy static files
        if ($this->site->options->skipStatic) {
            echo "Skipping static files\n";
        } else {
            $this->copyStatic($base . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "static");
        }
    }

    public function scan(string $dir): void
    {
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
                    $page = $this->parser->parse($path);
                    if ($page) {
                        $this->pages->add($page);
                    }
                } elseif (is_dir($path)) {
                    if (
                        strpos($path, DIRECTORY_SEPARATOR . "app") === false
                        && strpos($path, DIRECTORY_SEPARATOR . "bootstrap") === false
                        && strpos($path, DIRECTORY_SEPARATOR . "vendor") === false
                        && strpos($path, DIRECTORY_SEPARATOR . "resources") === false
                        && strpos($path, DIRECTORY_SEPARATOR . "data") === false
                        && strpos($path, DIRECTORY_SEPARATOR . $this->site->paths->outputDir) === false
                    ) {
                        $this->scan($path);
                    }
                }
            }
        }
    }

    public function generateHTMLFiles(): void
    {
        foreach ($this->pages as $page) {
            $this->createHTMLFile($page);
            $this->createGeminiFile($page);
            $this->createGopherFile($page);
        }
    }

    private function createHTMLFile(Page $page): void
    {
        $base = $this->site->paths->baseDir;
        $site = $this->site;
        // Expose $p, $pages and $site to the global scope for view template compatibility
        global $p, $site, $pages;
        $p = $page;
        $pages = $this->pages;

        if (in_array("draft", $page->metadata->tags)) {
            return;
        }

        $destination = str_replace("/", DIRECTORY_SEPARATOR, $page->slug);
        $destination = trim($destination, DIRECTORY_SEPARATOR);
        $destination = preg_replace(
            "/^" . preg_quote($this->site->paths->contentDir, '/') . "/",
            "",
            $destination
        );
        $destination = trim($destination, DIRECTORY_SEPARATOR);

        $outDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir;

        if (str_ends_with($destination, '.html')) {
            $dir = dirname($outDir . DIRECTORY_SEPARATOR . $destination);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $destinationFile = $outDir . DIRECTORY_SEPARATOR . $destination;
            echo "Built " . $page->slug . "\n";
        } else {
            if (!is_dir($outDir . DIRECTORY_SEPARATOR . $destination)) {
                mkdir($outDir . DIRECTORY_SEPARATOR . $destination, 0777, true);
            }
            $destinationFile = $outDir
                . DIRECTORY_SEPARATOR
                . $destination
                . DIRECTORY_SEPARATOR
                . "index.html";
            echo "Built " . $page->slug . "index.html" . "\n";
        }
        ob_start();
        // phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative
        include $base . DIRECTORY_SEPARATOR . "resources/views/" . $page->metadata->layout . ".php"; // NOSONAR
        $fileContent = ob_get_clean();

        if (isset($this->site->options->htmlpostprocessing)) {
            if ($this->site->options->htmlpostprocessing == "beautify" || $this->site->options->dev) {
                $fileContent = Helper::beautifyhtml($fileContent);
            }
            if ($this->site->options->htmlpostprocessing == "minify" && !$this->site->options->dev) {
                $fileContent = Helper::minifyhtml($fileContent);
            }
        }

        file_put_contents($destinationFile, $fileContent);
    }

    public function generateFeed(): void
    {
        $base = $this->site->paths->baseDir;
        $site = $this->site;
        $pages = $this->pages;
        // Expose to global scope for view template compatibility
        global $pages, $site;

        $file = $base . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "views" . DIRECTORY_SEPARATOR . "feed" . ".php";
        if (file_exists($file) && is_readable($file)) {
            include $file;
        }
    }

    public function copyAssets(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $base = $this->site->paths->baseDir;
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry !== "." && $entry !== "..") {
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($path)) {
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    if ($ext === "js" || $ext === "css") {
                        $filename = pathinfo($path, PATHINFO_FILENAME);
                        $assetsDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir . DIRECTORY_SEPARATOR . "assets";

                        if (!is_dir($assetsDir)) {
                            mkdir($assetsDir, 0777, true);
                        }

                        copy(
                            $path,
                            $assetsDir . DIRECTORY_SEPARATOR . $filename . "." . $ext
                        );
                    }
                } else {
                    $this->copyAssets($path);
                }
            }
        }
    }

    public function copyStatic(string $dir): bool
    {
        $base = $this->site->paths->baseDir;

        if (!is_dir($dir)) {
            return false;
        }

        echo "Copying static files\n";
        $this->copyStaticFiles($dir, $base);

        if ($this->site->options->dev) {
            $this->copyLiveJsFile($base);
        }

        return true;
    }

    private function copyStaticFiles(string $dir, string $base): void
    {
        $entries = Helper::getDirContents($dir);

        foreach ($entries as $entry) {
            if ($this->shouldSkipEntry($entry)) {
                continue;
            }

            $destination = $this->getDestinationPath($entry, $dir, $base);

            if ($this->shouldCopyFile($entry, $destination)) {
                $this->ensureDestinationDirectoryExists($destination);
                copy($entry, $destination);
            }
        }
    }

    private function shouldSkipEntry(string $entry): bool
    {
        return $entry === "." || $entry === "..";
    }

    private function getDestinationPath(string $entry, string $dir, string $base): string
    {
        $path = str_replace($dir . DIRECTORY_SEPARATOR, "", $entry);
        $filepath = pathinfo($path, PATHINFO_DIRNAME);
        $fullfilename = pathinfo($path, PATHINFO_BASENAME);

        return $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir . DIRECTORY_SEPARATOR . $filepath . DIRECTORY_SEPARATOR . $fullfilename;
    }

    private function shouldCopyFile(string $source, string $destination): bool
    {
        return is_file($source)
            && (!is_file($destination)
                || filemtime($source) > filemtime($destination)
                || $this->site->options->forceStaticOverride);
    }

    private function ensureDestinationDirectoryExists(string $destination): void
    {
        $directory = pathinfo($destination, PATHINFO_DIRNAME);

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    private function copyLiveJsFile(string $base): void
    {
        $jsDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir . DIRECTORY_SEPARATOR . "js";

        if (!is_dir($jsDir)) {
            mkdir($jsDir, 0777, true);
        }

        copy($base . "/resources/views/livejs/live.js", $jsDir . "/live.js");
    }

    private function createGeminiFile(Page $page): void
    {
        if (in_array("draft", $page->metadata->tags)) {
            return;
        }

        $base = $this->site->paths->baseDir;
        $destination = str_replace("/", DIRECTORY_SEPARATOR, $page->slug);
        $destination = trim($destination, DIRECTORY_SEPARATOR);
        $destination = preg_replace(
            "/^" . preg_quote($this->site->paths->contentDir, '/') . "/",
            "",
            $destination
        );
        $destination = trim($destination, DIRECTORY_SEPARATOR);

        $outDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir;
        if (!is_dir($outDir . DIRECTORY_SEPARATOR . $destination)) {
            mkdir($outDir . DIRECTORY_SEPARATOR . $destination, 0777, true);
        }

        $destinationFile = $outDir
            . DIRECTORY_SEPARATOR
            . $destination
            . DIRECTORY_SEPARATOR
            . "index.gmi";

        echo "Built " . $page->slug . "index.gmi\n";

        $astParser = new ASTParser();
        $gemtextRenderer = new GemtextRenderer();

        $rawBody = $page->rawBody ?? '';
        $ast = $astParser->parse($rawBody);
        
        $title = $page->title;
        $dateStr = $page->localizeddate;
        $author = $this->site->metadata->author;

        $gmiContent = "# {$title}\n";
        if ($dateStr) {
            $gmiContent .= "Published: {$dateStr}";
            if ($author) {
                $gmiContent .= " by {$author}";
            }
            $gmiContent .= "\n";
        }
        $gmiContent .= "\n";

        $gmiContent .= $gemtextRenderer->render($ast);
        $gmiContent .= "\n=> / Back to Home\n";

        file_put_contents($destinationFile, $gmiContent);
    }

    private function createGopherFile(Page $page): void
    {
        if (in_array("draft", $page->metadata->tags)) {
            return;
        }

        $base = $this->site->paths->baseDir;
        $destination = str_replace("/", DIRECTORY_SEPARATOR, $page->slug);
        $destination = trim($destination, DIRECTORY_SEPARATOR);
        $destination = preg_replace(
            "/^" . preg_quote($this->site->paths->contentDir, '/') . "/",
            "",
            $destination
        );
        $destination = trim($destination, DIRECTORY_SEPARATOR);

        $outDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir;
        if (!is_dir($outDir . DIRECTORY_SEPARATOR . $destination)) {
            mkdir($outDir . DIRECTORY_SEPARATOR . $destination, 0777, true);
        }

        $destinationFile = $outDir
            . DIRECTORY_SEPARATOR
            . $destination
            . DIRECTORY_SEPARATOR
            . "gophermap";

        echo "Built " . $page->slug . "gophermap\n";

        $host = 'gopher.example.com';
        if ($this->site->metadata->fqdn) {
            $parsedUrl = parse_url($this->site->metadata->fqdn);
            $host = $parsedUrl['host'] ?? $host;
        }

        $astParser = new ASTParser();
        $gophermapRenderer = new GophermapRenderer($host, 70);

        $rawBody = $page->rawBody ?? '';
        $ast = $astParser->parse($rawBody);

        $title = $page->title;
        $dateStr = $page->localizeddate;
        $author = $this->site->metadata->author;

        $formatInfo = function(string $text): string {
            return "i{$text}\t\t(null)\t0\r\n";
        };

        $gopherContent = $formatInfo("=== {$title} ===");
        if ($dateStr) {
            $meta = "Published: {$dateStr}";
            if ($author) {
                $meta .= " by {$author}";
            }
            $gopherContent .= $formatInfo($meta);
        }
        $gopherContent .= $formatInfo("");

        $gopherContent .= $gophermapRenderer->render($ast);
        $gopherContent .= $formatInfo("");
        $gopherContent .= "1Back to Home\t/\t{$host}\t70\r\n";

        file_put_contents($destinationFile, $gopherContent);
    }

    public function generateTwtxt(): void
    {
        $base = $this->site->paths->baseDir;
        $outDir = $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDir;
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }

        // 1. Generate local feed: public/twtxt.txt
        $twtxtManager = new \Indieinabox\Twtxt\TwtxtManager();
        $feedFile = $outDir . DIRECTORY_SEPARATOR . 'twtxt.txt';

        echo "Generating twtxt.txt feed...\n";
        $twtxtManager->generateFeed(
            iterator_to_array($this->pages),
            $feedFile,
            $this->site->metadata->fqdn,
            $this->site->twtxt
        );

        // 2. Fetch aggregated timeline & mentions if subscriptions/hubs are configured
        echo "Fetching twtxt timeline and mentions...\n";
        $cacheDir = $base . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'twtxt_cache';

        $timelineEntries = [];
        $mentionEntries = [];

        if (!empty($this->site->twtxt->following)) {
            $timelineEntries = $twtxtManager->fetchTimeline($this->site->twtxt->following, $cacheDir);
        }
        if (!empty($this->site->twtxt->hubs)) {
            $mentionEntries = $twtxtManager->fetchHubMentions($this->site->twtxt->hubs, $this->site->metadata->fqdn);
        }

        // 3. Compile the static timeline page: public/timeline/index.html
        echo "Compiling timeline static page...\n";
        $timelinePage = Page::fromArray([
            'title' => 'Timeline',
            'layout' => 'timeline',
            'slug' => 'timeline/',
            'date' => time(),
            'content' => '',
            'originalcontent' => ''
        ]);

        // Expose timeline & mentions globally for timeline.php view template
        global $timeline, $mentions;
        $timeline = $timelineEntries;
        $mentions = $mentionEntries;

        $layoutFile = $base . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'timeline.php';
        if (file_exists($layoutFile) && is_readable($layoutFile)) {
            $this->createHTMLFile($timelinePage);
        } else {
            echo "Skipping timeline static page compilation: timeline layout not found.\n";
        }
    }
}
