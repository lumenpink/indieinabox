<?php

declare(strict_types=1);

namespace Indieinabox\SiteBuilder;

use Indieinabox\Site;
use Indieinabox\ThemeManager;

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

        ThemeManager::copyViewAssets($dir, $base, $this->site->paths->outputDirHtml);
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
        ThemeManager::copyStaticFiles($dir, $base, $this->site->paths->outputDirHtml);

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
            ThemeManager::copyStaticFiles($contentMediaDir, $base, $destMedia);
        }

        $microsubMediaDir = $base . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'microsub' . DIRECTORY_SEPARATOR . 'media';
        if (is_dir($microsubMediaDir)) {
            echo "Copying microsub media files\n";
            ThemeManager::copyStaticFiles($microsubMediaDir, $base, $destMedia . DIRECTORY_SEPARATOR . 'microsub');
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
