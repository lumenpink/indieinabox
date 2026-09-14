<?php

declare(strict_types=1);

namespace Indieinabox\SiteBuilder;

use Indieinabox\Helper;
use Indieinabox\Site;
use Indieinabox\SiteBuilder;

/**
 * Handles publishing and lifecycle of static files, theme assets, and media.
 * Also responsible for garbage collection of orphaned build files.
 */
class AssetPublisher
{
    private Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Copies global assets from the theme's views directory to the public HTML output directory.
     *
     * @param string $dir The source directory containing theme assets.
     */
    public function publishViewAssets(string $dir): void
    {
        $base = $this->site->paths->baseDir;

        if (!is_dir($dir) && !class_exists('\\DefaultTheme')) {
            return;
        }

        $this->copyViewAssets($dir, $base, $this->site->paths->outputDirHtml);
    }

    /**
     * Copies general static files from the given directory to the output HTML directory.
     *
     * @param string $dir The source directory containing static files.
     * @return bool True if copy was successful or theme was available, false otherwise.
     */
    public function publishStaticFiles(string $dir): bool
    {
        $base = $this->site->paths->baseDir;

        if (!is_dir($dir) && !class_exists('\\DefaultTheme')) {
            return false;
        }

        echo "Copying static files\n";
        $this->copyStaticFiles($dir, $base, $this->site->paths->outputDirHtml);

        return true;
    }

    /**
     * Copies static media files from the content directory and microsub data directory
     * to the public media output directory.
     */
    public function publishMedia(): void
    {
        $base = $this->site->paths->baseDir;
        $contentMediaDir = rtrim($this->site->paths->getContentPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'media';
        $destMedia = $this->site->paths->outputDirMedia;
        if (is_dir($contentMediaDir)) {
            echo "Copying media files\n";
            $this->copyStaticFiles($contentMediaDir, $base, $destMedia);
        }

        $microsubMediaDir = $base . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'media';
        if (is_dir($microsubMediaDir)) {
            echo "Copying microsub media files\n";
            $this->copyStaticFiles($microsubMediaDir, $base, $destMedia . DIRECTORY_SEPARATOR . 'microsub');
        }
    }

    /**
     * Copies static files. If the directory exists on disk, it uses file system copy.
     * Otherwise, it writes the embedded static files to the destination.
     *
     * @param string $dir The source directory.
     * @param string $base The base project directory.
     * @param string $outputDir The destination output directory.
     * @return void
     */
    public function copyStaticFiles(string $dir, string $base, string $outputDir): void
    {
        if (is_dir($dir)) {
            $this->copyFromDisk($dir, $base, $outputDir);
        } elseif (class_exists('\\DefaultTheme')) {
            $staticFiles = \DefaultTheme::getStaticFiles();
            foreach ($staticFiles as $relativePath => $content) {
                if (strpos($relativePath, 'static/') === 0) {
                    $destPath = substr($relativePath, 7);
                } else {
                    $destPath = $relativePath;
                }

                $destination = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . ltrim($destPath, '/');
                $destDir = dirname($destination);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }
                file_put_contents($destination, $content);
                SiteBuilder::addManifest($destination);
            }
        }
    }

    /**
     * Copies a directory tree from disk to the public output directory.
     *
     * @param string $dir The source directory.
     * @param string $base The base path of the project.
     * @param string $outputDir The destination output directory.
     * @return void
     */
    private function copyFromDisk(string $dir, string $base, string $outputDir): void
    {
        $entries = Helper::getDirContents($dir);

        foreach ($entries as $entry) {
            if ($entry === "." || $entry === "..") {
                continue;
            }

            $path = str_replace($dir . DIRECTORY_SEPARATOR, "", $entry);
            $destination = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . ltrim($path, '/');

            if (is_file($entry)) {
                $destDir = dirname($destination);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0777, true);
                }
                copy($entry, $destination);
                SiteBuilder::addManifest($destination);
            }
        }
    }

    /**
     * Copies global assets from the `views/assets` directory to the public root.
     *
     * @param string $dir The views directory.
     * @param string $base The base path of the project.
     * @param string $outputDir The destination output directory.
     * @return void
     */
    public function copyViewAssets(string $dir, string $base, string $outputDir): void
    {
        if (is_dir($dir)) {
            $this->copyAssetsFromDisk($dir, $base, $outputDir);
        } elseif (class_exists('\\DefaultTheme')) {
            $views = \DefaultTheme::getViews();
            foreach ($views as $relativePath => $content) {
                $ext = pathinfo($relativePath, PATHINFO_EXTENSION);
                if ($ext === "js" || $ext === "css") {
                    $filename = pathinfo($relativePath, PATHINFO_FILENAME);
                    $assetsDir = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . "assets";

                    if (!is_dir($assetsDir)) {
                        mkdir($assetsDir, 0777, true);
                    }
                    $destPath = $assetsDir . DIRECTORY_SEPARATOR . $filename . "." . $ext;
                    file_put_contents($destPath, $content);
                    SiteBuilder::addManifest($destPath);
                }
            }
        }
    }

    /**
     * Recursively copies assets (.js, .css) from a theme's directory to the public output.
     *
     * @param string $dir The source directory.
     * @param string $base The base project directory.
     * @param string $outputDir The destination output directory.
     * @return void
     */
    private function copyAssetsFromDisk(string $dir, string $base, string $outputDir): void
    {
        if (!is_dir($dir)) {
            return;
        }
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
                        $assetsDir = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . "assets";

                        if (!is_dir($assetsDir)) {
                            mkdir($assetsDir, 0777, true);
                        }
                        $destPath = $assetsDir . DIRECTORY_SEPARATOR . $filename . "." . $ext;
                        copy($path, $destPath);
                        SiteBuilder::addManifest($destPath);
                    }
                } elseif (is_dir($path)) {
                    $this->copyAssetsFromDisk($path, $base, $outputDir);
                }
            }
        }
    }

    /**
     * Scans output directories and removes files not registered in the manifest.
     * Removes empty directories as well.
     *
     * @param array<string, bool> $manifest
     */
    public function garbageCollect(array $manifest): void
    {
        echo "Running Garbage Collector...\n";
        $base = $this->site->paths->baseDir;
        $dirs = [
            $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirHtml,
            $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGemini,
            $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirGopher,
            $base . DIRECTORY_SEPARATOR . $this->site->paths->outputDirMedia,
        ];

        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $this->cleanOrphanedFiles($dir, $manifest);
            }
        }
    }

    /**
     * Recursively deletes orphaned files and empty directories.
     *
     * @param array<string, bool> $manifest
     */
    public function cleanOrphanedFiles(string $dir, array $manifest): void
    {
        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->cleanOrphanedFiles($path, $manifest);
                // After cleaning contents, check if directory is empty
                $contents = scandir($path);
                if ($contents !== false && count($contents) <= 2) {
                    rmdir($path);
                }
            } elseif (is_file($path)) {
                // Remove if not in manifest
                if (!isset($manifest[$path])) {
                    unlink($path);
                }
            }
        }
    }
}
