<?php

declare(strict_types=1);

namespace Indieinabox\Theme;

use Indieinabox\Support\FileUtils;

/**
 * Class ThemeStarterService
 *
 * Scaffolds, packages and manages official starter themes (Cyberpunk Dark, Minimal Reader, Digital Garden),
 * providing 1-click installation and standalone ZIP generation for offline downloads.
 */
class ThemeStarterService
{
    /**
     * Resolves the directory where starter theme source packages reside.
     *
     * @param ?string $baseDir
     * @return string
     */
    public static function getStartersSourceDir(?string $baseDir = null): string
    {
        $base = $baseDir ?? dirname(__DIR__, 2);
        return rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'themes';
    }

    /**
     * Returns metadata for all official starter themes.
     *
     * @param ?string $baseDir
     * @return array<string, array<string, mixed>>
     */
    public static function getOfficialThemes(?string $baseDir = null): array
    {
        $sourceDir = self::getStartersSourceDir($baseDir);
        $official = [
            'cyberpunk-dark' => [
                'name' => 'Cyberpunk Dark',
                'slug' => 'cyberpunk-dark',
                'version' => '1.0.0',
                'description' => 'Retro-futuristic terminal aesthetic with neon phosphor accents, monospaced typography, and high contrast WCAG AAA compliance.',
                'author' => 'IndieInABox Core',
                'badge' => '⚡ Cyberpunk / Terminal',
                'preview_colors' => ['#0a0c10', '#00ff66', '#00f0ff', '#ff0055'],
            ],
            'minimal-reader' => [
                'name' => 'Minimal Reader',
                'slug' => 'minimal-reader',
                'version' => '1.0.0',
                'description' => 'Distraction-free editorial reading experience with elegant typography, generous whitespace, and serene readability.',
                'author' => 'IndieInABox Core',
                'badge' => '📖 Editorial / Clean',
                'preview_colors' => ['#faf9f6', '#24292e', '#0969da', '#57606a'],
            ],
            'digital-garden' => [
                'name' => 'Digital Garden',
                'slug' => 'digital-garden',
                'version' => '1.0.0',
                'description' => 'Botanical and interconnected knowledge garden theme emphasizing flowerbeds, plant maturity badges, and bi-directional thinking.',
                'author' => 'IndieInABox Core',
                'badge' => '🌱 Botanical / Knowledge',
                'preview_colors' => ['#f9f8f3', '#2c352e', '#2e6930', '#b85433'],
            ],
        ];

        // Enrich with theme.json metadata if available
        foreach ($official as $slug => &$info) {
            $manifestPath = $sourceDir . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'theme.json';
            if (file_exists($manifestPath)) {
                $decoded = json_decode((string) file_get_contents($manifestPath), true);
                if (is_array($decoded)) {
                    $info = array_merge($info, $decoded);
                }
            }
        }
        unset($info);

        return $official;
    }

    /**
     * Installs an official starter theme into the target themes directory with 1-click.
     *
     * @param string $slug Theme identifier.
     * @param ?string $targetThemesDir Destination directory (e.g. data/themes or themes).
     * @param ?string $baseDir
     * @return array{success: bool, slug?: string, error?: string}
     */
    public static function installOfficialTheme(
        string $slug,
        ?string $targetThemesDir = null,
        ?string $baseDir = null
    ): array {
        $slug = strtolower(trim($slug));
        $sourceDir = self::getStartersSourceDir($baseDir) . DIRECTORY_SEPARATOR . $slug;

        if (!is_dir($sourceDir)) {
            return [
                'success' => false,
                'error' => "Starter theme '{$slug}' does not exist in {$sourceDir}.",
            ];
        }

        $manifestFile = $sourceDir . DIRECTORY_SEPARATOR . 'theme.json';
        if (!file_exists($manifestFile)) {
            return [
                'success' => false,
                'error' => "Theme manifest theme.json missing in {$sourceDir}.",
            ];
        }

        $manifest = json_decode((string) file_get_contents($manifestFile), true) ?: [];
        $check = ThemeManager::checkManifest($manifest);
        if (!$check['valid']) {
            return [
                'success' => false,
                'error' => implode(' ', $check['errors']),
            ];
        }

        $destBase = $targetThemesDir ?? (
            \Indieinabox\Core\Database::$dataDir ? \Indieinabox\Core\Database::$dataDir . DIRECTORY_SEPARATOR . 'themes' : 'themes'
        );

        if (!is_dir($destBase)) {
            @mkdir($destBase, 0755, true);
        }

        $destDir = rtrim($destBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $slug;
        if (is_dir($destDir)) {
            FileUtils::recursiveRmdir($destDir);
        }
        @mkdir($destDir, 0755, true);

        // Copy all files recursively
        self::copyRecursive($sourceDir, $destDir);

        return [
            'success' => true,
            'slug' => $slug,
            'manifest' => $manifest,
        ];
    }

    /**
     * Packages a theme directory into a standalone ZIP archive for download.
     *
     * @param string $slug
     * @param ?string $outputZipPath
     * @param ?string $baseDir
     * @return string|null Path to generated ZIP file, or null on failure.
     */
    public static function createZipPackage(
        string $slug,
        ?string $outputZipPath = null,
        ?string $baseDir = null
    ): ?string {
        $slug = strtolower(trim($slug));
        $sourceDir = self::getStartersSourceDir($baseDir) . DIRECTORY_SEPARATOR . $slug;

        if (!is_dir($sourceDir)) {
            return null;
        }

        $destZip = $outputZipPath ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . "theme-{$slug}.zip";
        if (file_exists($destZip)) {
            @unlink($destZip);
        }

        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($destZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($files as $file) {
                    $filePath = $file->getRealPath();
                    $relativePath = $slug . '/' . substr($filePath, strlen($sourceDir) + 1);

                    if ($file->isDir()) {
                        $zip->addEmptyDir($relativePath);
                    } elseif ($file->isFile()) {
                        $zip->addFile($filePath, $relativePath);
                    }
                }
                $zip->close();
                return file_exists($destZip) ? $destZip : null;
            }
        }

        // Fallback using system zip command
        $parentDir = dirname($sourceDir);
        $cmd = sprintf(
            'cd %s && zip -q -r %s %s 2>&1',
            escapeshellarg($parentDir),
            escapeshellarg($destZip),
            escapeshellarg($slug)
        );
        exec($cmd, $out, $ret);

        return ($ret === 0 && file_exists($destZip)) ? $destZip : null;
    }

    /**
     * Recursively copies a directory to destination.
     *
     * @param string $source
     * @param string $dest
     * @return void
     */
    private static function copyRecursive(string $source, string $dest): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $subPath = substr($item->getPathname(), strlen($source) + 1);
            $targetPath = $dest . DIRECTORY_SEPARATOR . $subPath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    @mkdir($targetPath, 0755, true);
                }
            } else {
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0755, true);
                }
                copy($item->getPathname(), $targetPath);
            }
        }
    }
}
