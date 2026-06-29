<?php

declare(strict_types=1);

namespace Indieinabox;

class ThemeManager
{
    /**
     * Includes a view file. If the file exists on disk, it uses standard include.
     * Otherwise, it tries to load it from the embedded DefaultTheme fallback.
     *
     * @param string $filePath
     * @param array<string, mixed> $vars
     */
    public static function loadView(string $filePath, array $vars = []): void
    {
        extract($vars);
        if (file_exists($filePath)) {
            include $filePath;
            return;
        }

        // Try to load from embedded theme if compiled
        if (class_exists('\\DefaultTheme')) {
            global $site;
            $themeDir = isset($site) && isset($site->paths->themeDir) ? $site->paths->themeDir : 'resources';

            // Extract relative path inside the theme folder
            // e.g. /var/www/resources/views/page.php -> views/page.php
            $pos = strpos($filePath, DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR);
            if ($pos !== false) {
                $relativePath = substr($filePath, $pos + strlen(DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR));
            } else {
                $relativePath = basename($filePath);
            }

            // Standardize path separator to forward slash for the embedded keys
            $relativePath = str_replace('\\', '/', $relativePath);

            $content = \DefaultTheme::getView($relativePath);
            if ($content !== null) {
                eval('?>' . $content);
                return;
            }
        }

        // If neither exists, print a helpful error instead of crashing silently
        echo "<!-- Theme file not found: " . htmlspecialchars($filePath) . " -->\n";
    }

    /**
     * Copies static files. If the directory exists on disk, it uses file system copy.
     * Otherwise, it writes the embedded static files to the destination.
     */
    public static function copyStaticFiles(string $dir, string $base, string $outputDir): void
    {
        if (is_dir($dir)) {
            // Read from filesystem using file iteration
            self::copyFromDisk($dir, $base, $outputDir);
        } else {
            // Read from embedded theme
            if (class_exists('\\DefaultTheme')) {
                $staticFiles = \DefaultTheme::getStaticFiles();
                foreach ($staticFiles as $relativePath => $content) {
                    // Extract just the part after static/
                    // e.g. static/dist/app.css -> dist/app.css
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
                }
            }
        }
    }

    private static function copyFromDisk(string $dir, string $base, string $outputDir): void
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
            }
        }
    }

    public static function copyViewAssets(string $dir, string $base, string $outputDir): void
    {
        if (is_dir($dir)) {
            self::copyAssetsFromDisk($dir, $base, $outputDir);
        } else {
            if (class_exists('\\DefaultTheme')) {
                $views = \DefaultTheme::getViews();
                foreach ($views as $relativePath => $content) {
                    $ext = pathinfo($relativePath, PATHINFO_EXTENSION);
                    if ($ext === "js" || $ext === "css") {
                        $filename = pathinfo($relativePath, PATHINFO_FILENAME);
                        $assetsDir = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . "assets";

                        if (!is_dir($assetsDir)) {
                            mkdir($assetsDir, 0777, true);
                        }

                        file_put_contents($assetsDir . DIRECTORY_SEPARATOR . $filename . "." . $ext, $content);
                    }
                }
            }
        }
    }

    private static function copyAssetsFromDisk(string $dir, string $base, string $outputDir): void
    {
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

                        copy($path, $assetsDir . DIRECTORY_SEPARATOR . $filename . "." . $ext);
                    }
                } elseif (is_dir($path)) {
                    self::copyAssetsFromDisk($path, $base, $outputDir);
                }
            }
        }
    }
}
