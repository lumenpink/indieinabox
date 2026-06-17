<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Markdown\FileProcessor;
use Indieinabox\Markdown\ContentProcessor;
use Indieinabox\Markdown\LanguageProcessor;
use Indieinabox\Translations\UrlTranslations;

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
        $parsedown = new Parsedown();
        global $urltranslations;

        $fileProcessor     = new FileProcessor($this->site, $base);
        $contentProcessor  = new ContentProcessor($parsedown);
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
        if (!is_dir($outDir . DIRECTORY_SEPARATOR . $destination)) {
            mkdir($outDir . DIRECTORY_SEPARATOR . $destination, 0777, true);
        }

        $destinationFile = $outDir
            . DIRECTORY_SEPARATOR
            . $destination
            . DIRECTORY_SEPARATOR
            . "index.html";

        echo "Built " . $page->slug . "index.html" . "\n";
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
}
