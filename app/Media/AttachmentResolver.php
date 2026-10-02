<?php

declare(strict_types=1);

namespace Indieinabox\Media;

use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\SiteBuilder;

/**
 * Resolves attachment and image references across the Obsidian vault
 * following Obsidian link resolution rules, and publishes them into the
 * unified shared media directory across all protocols.
 */
class AttachmentResolver
{
    /**
     * Cache of vault files indexed by lowercase basename.
     * @var array<string, array<string, list<string>>> [vaultRoot => [lowercase_basename => [path1, path2]]]
     */
    private static array $vaultFileIndex = [];

    /**
     * Map of published media files to prevent duplicate collisions.
     * @var array<string, array<string, string>> [outputDirMedia => [cleanBasename => sourceSha1]]
     */
    private static array $publishedBasenames = [];

    /**
     * Map of source file to assigned published media filename.
     * @var array<string, string> [sourceFilePath => publishedFilename]
     */
    private static array $sourceToPublished = [];

    /**
     * Finds the vault root directory containing the content directory or .obsidian folder.
     */
    public static function getVaultRoot(Site $site): string
    {
        $contentPath = rtrim($site->paths->getContentPath(), DIRECTORY_SEPARATOR);

        if (is_dir($contentPath . DIRECTORY_SEPARATOR . '.obsidian')) {
            return $contentPath;
        }

        $parent = dirname($contentPath);
        if (is_dir($parent . DIRECTORY_SEPARATOR . '.obsidian')) {
            return $parent;
        }

        return $parent;
    }

    /**
     * Reads the configured attachment folder from .obsidian/app.json if available.
     *
     * @return list<string> List of attachment folder names in order of preference.
     */
    public static function getAttachmentFolders(string $vaultRoot): array
    {
        $folders = [];
        $appJson = $vaultRoot . DIRECTORY_SEPARATOR . '.obsidian' . DIRECTORY_SEPARATOR . 'app.json';

        if (file_exists($appJson)) {
            $data = json_decode((string) file_get_contents($appJson), true);
            if (is_array($data) && !empty($data['attachmentFolderPath']) && is_string($data['attachmentFolderPath'])) {
                $configured = trim($data['attachmentFolderPath'], '/\\');
                if ($configured !== '') {
                    $folders[] = $configured;
                }
            }
        }

        // Standard Obsidian/Markdown attachment fallbacks
        foreach (['99_anexo', 'anexos', 'attachments', 'media'] as $fallback) {
            if (!in_array($fallback, $folders, true)) {
                $folders[] = $fallback;
            }
        }

        return $folders;
    }

    /**
     * Resolves the full path of an attachment file in the vault.
     *
     * @param string $target Target reference (e.g. "image.jpg", "99_anexo/image.jpg", "/media/image.jpg")
     * @param string $referencingFilePath Full path to the markdown file referencing the attachment
     * @param Site $site
     * @return string|null Absolute path to the resolved attachment file or null if not found
     */
    public static function resolve(string $target, string $referencingFilePath, Site $site): ?string
    {
        $target = trim($target);
        if ($target === '' || preg_match('#^(https?:)?//#i', $target)) {
            return null;
        }

        // Strip leading slash or '/media/' prefix if present
        $cleanTarget = ltrim($target, '/\\');
        if (str_starts_with($cleanTarget, 'media/')) {
            $cleanTarget = substr($cleanTarget, 6);
        }

        $vaultRoot = self::getVaultRoot($site);
        $contentPath = rtrim($site->paths->getContentPath(), DIRECTORY_SEPARATOR);
        $noteDir = dirname($referencingFilePath);
        $attachmentFolders = self::getAttachmentFolders($vaultRoot);

        // 1. Direct path relative to referencing note
        $candidate = $noteDir . DIRECTORY_SEPARATOR . $cleanTarget;
        if (is_file($candidate)) {
            return realpath($candidate) ?: $candidate;
        }

        // 2. Direct path relative to content dir
        $candidate = $contentPath . DIRECTORY_SEPARATOR . $cleanTarget;
        if (is_file($candidate)) {
            return realpath($candidate) ?: $candidate;
        }

        // 3. Direct path relative to vault root
        $candidate = $vaultRoot . DIRECTORY_SEPARATOR . $cleanTarget;
        if (is_file($candidate)) {
            return realpath($candidate) ?: $candidate;
        }

        $targetBase = basename($cleanTarget);

        // 4. Configured attachment folders (vault root & content dir)
        foreach ($attachmentFolders as $folder) {
            $candidates = [
                $vaultRoot . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $cleanTarget,
                $vaultRoot . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $targetBase,
                $contentPath . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $cleanTarget,
                $contentPath . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $targetBase,
            ];
            foreach ($candidates as $cand) {
                if (is_file($cand)) {
                    return realpath($cand) ?: $cand;
                }
            }
        }

        // 5. Ancestor directories of note up to vault root
        $curr = $noteDir;
        while ($curr !== $vaultRoot && $curr !== dirname($curr)) {
            $curr = dirname($curr);
            $cand = $curr . DIRECTORY_SEPARATOR . $cleanTarget;
            if (is_file($cand)) {
                return realpath($cand) ?: $cand;
            }
        }

        // 6. Vault-wide index lookup with proximity ranking for duplicate basenames
        return self::resolveFromVaultIndex($targetBase, $referencingFilePath, $vaultRoot);
    }

    /**
     * Resolves a basename from the vault-wide index, ranking by path proximity.
     */
    private static function resolveFromVaultIndex(string $basename, string $referencingFilePath, string $vaultRoot): ?string
    {
        self::ensureVaultIndex($vaultRoot);

        $lower = strtolower($basename);
        if (!isset(self::$vaultFileIndex[$vaultRoot][$lower])) {
            return null;
        }

        $matches = self::$vaultFileIndex[$vaultRoot][$lower];
        if (count($matches) === 1) {
            return $matches[0];
        }

        // Multiple files with the same name: rank by proximity to referencing file
        $bestMatch = null;
        $bestScore = -1;

        $refParts = explode(DIRECTORY_SEPARATOR, trim($referencingFilePath, DIRECTORY_SEPARATOR));

        foreach ($matches as $match) {
            $matchParts = explode(DIRECTORY_SEPARATOR, trim($match, DIRECTORY_SEPARATOR));
            $score = 0;
            $len = min(count($refParts), count($matchParts));
            for ($i = 0; $i < $len; $i++) {
                if ($refParts[$i] === $matchParts[$i]) {
                    $score++;
                } else {
                    break;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $match;
            }
        }

        return $bestMatch;
    }

    /**
     * Builds and caches an in-memory index of all files in the vault.
     */
    private static function ensureVaultIndex(string $vaultRoot): void
    {
        if (isset(self::$vaultFileIndex[$vaultRoot])) {
            return;
        }

        self::$vaultFileIndex[$vaultRoot] = [];
        if (!is_dir($vaultRoot)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($vaultRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $path = $item->getPathname();
                // Skip hidden files/directories (.git, .trash, etc.)
                if (str_contains($path, DIRECTORY_SEPARATOR . '.') && !str_contains($path, DIRECTORY_SEPARATOR . '.obsidian')) {
                    continue;
                }
                $base = strtolower($item->getBasename());
                self::$vaultFileIndex[$vaultRoot][$base][] = $path;
            }
        }
    }

    /**
     * Publishes an image file to the unified /media/ destination across protocols,
     * with duplicate collision protection.
     *
     * @param string $sourceFile Resolved original image path
     * @param Site $site
     * @return array{webPath: string, originalWebPath: string, gifName: string, originalName: string}
     */
    public static function publishImage(string $sourceFile, Site $site, ?int $desiredWidth = null): array
    {
        $base = rtrim($site->paths->baseDir, DIRECTORY_SEPARATOR);
        $outputMedia = $site->paths->outputDirMedia ?: 'public_media';
        $outputHtml = $site->paths->outputDirHtml ?: 'public_html';
        $outputGemini = $site->paths->outputDirGemini ?: 'public_gemini';
        $outputGopher = $site->paths->outputDirGopher ?: 'public_gopher';

        $focalWidth = ($desiredWidth !== null && $desiredWidth >= 64 && $desiredWidth <= 1920) ? $desiredWidth : 512;
        $cacheKey = $sourceFile . '@' . $focalWidth;

        // Check if already published for this source path and width
        if (isset(self::$sourceToPublished[$cacheKey])) {
            $assigned = self::$sourceToPublished[$cacheKey];
            $pathInfo = pathinfo($assigned);
            $suffix = $focalWidth !== 512 ? '_' . $focalWidth : '';
            $gifName = $pathInfo['filename'] . $suffix . '.gif';
            return [
                'webPath' => '/media/' . $gifName,
                'originalWebPath' => '/media/' . $assigned,
                'gifName' => $gifName,
                'originalName' => $assigned,
            ];
        }

        $sourceSha1 = sha1_file($sourceFile) ?: '';
        $cleanBasename = basename($sourceFile);
        $pathInfo = pathinfo($cleanBasename);
        $filenameOnly = $pathInfo['filename'];
        $ext = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

        // Disambiguation for collisions
        if (isset(self::$publishedBasenames[$outputMedia][$cleanBasename])) {
            if (self::$publishedBasenames[$outputMedia][$cleanBasename] === $sourceSha1) {
                $finalOriginalName = $cleanBasename;
            } else {
                $parentName = basename(dirname($sourceFile));
                $shortHash = substr($sourceSha1, 0, 4);
                $finalOriginalName = $parentName . '_' . $filenameOnly . '-' . $shortHash . $ext;
            }
        } else {
            self::$publishedBasenames[$outputMedia][$cleanBasename] = $sourceSha1;
            $finalOriginalName = $cleanBasename;
        }

        self::$sourceToPublished[$cacheKey] = $finalOriginalName;
        $finalInfo = pathinfo($finalOriginalName);
        $suffix = $focalWidth !== 512 ? '_' . $focalWidth : '';
        $gifName = $finalInfo['filename'] . $suffix . '.gif';
        $gifNameGlobal = $finalInfo['filename'] . $suffix . '_global.gif';
        $gifNameThumb = $finalInfo['filename'] . '_thumb.gif';

        // Destination directories
        $mediaDirs = [
            $base . DIRECTORY_SEPARATOR . $outputMedia,
            $base . DIRECTORY_SEPARATOR . $outputHtml . DIRECTORY_SEPARATOR . 'media',
            $base . DIRECTORY_SEPARATOR . $outputGemini . DIRECTORY_SEPARATOR . 'media',
            $base . DIRECTORY_SEPARATOR . $outputGopher . DIRECTORY_SEPARATOR . 'media',
        ];

        foreach ($mediaDirs as $mDir) {
            if (!is_dir($mDir)) {
                @mkdir($mDir, 0777, true);
            }
        }

        $canonicalMediaDir = $mediaDirs[0];
        $canonicalGif = $canonicalMediaDir . DIRECTORY_SEPARATOR . $gifName;
        $canonicalGlobal = $canonicalMediaDir . DIRECTORY_SEPARATOR . $gifNameGlobal;
        $canonicalThumb = $canonicalMediaDir . DIRECTORY_SEPARATOR . $gifNameThumb;
        $canonicalOriginal = $canonicalMediaDir . DIRECTORY_SEPARATOR . $finalOriginalName;

        // 1. Atkinson dithered GIF (theme colors, custom width)
        $colors = self::getThemeColors();
        ImageProcessor::ditherImageToGif(
            $sourceFile,
            $canonicalGif,
            $focalWidth,
            $colors['bg'],
            $colors['fg'],
            true
        );

        // 2. Global GIF & Thumbnail
        $globalColors = [
            'bg' => [244, 241, 234], // #F4F1EA
            'fg' => [44, 46, 47],    // #2C2E2F
        ];

        ImageProcessor::ditherImageToGif(
            $sourceFile,
            $canonicalGlobal,
            $focalWidth,
            $globalColors['bg'],
            $globalColors['fg'],
            true
        );

        ImageProcessor::createThumbnail(
            $sourceFile,
            $canonicalThumb,
            96,
            $globalColors['bg'],
            $globalColors['fg']
        );

        // 3. Copy original
        if (!file_exists($canonicalOriginal)) {
            copy($sourceFile, $canonicalOriginal);
        }

        // Mirror files to html/media, gemini/media, gopher/media and register in manifest
        $filesToMirror = [$gifName, $gifNameGlobal, $gifNameThumb, $finalOriginalName];

        foreach ($mediaDirs as $targetDir) {
            foreach ($filesToMirror as $file) {
                $src = $canonicalMediaDir . DIRECTORY_SEPARATOR . $file;
                $dst = $targetDir . DIRECTORY_SEPARATOR . $file;
                if ($targetDir !== $canonicalMediaDir && file_exists($src)) {
                    if (!file_exists($dst) || filemtime($src) > filemtime($dst)) {
                        copy($src, $dst);
                    }
                }
                if (file_exists($dst)) {
                    SiteBuilder::addManifest($dst);
                }
            }
        }

        return [
            'webPath' => '/media/' . $gifName,
            'originalWebPath' => '/media/' . $finalOriginalName,
            'gifName' => $gifName,
            'originalName' => $finalOriginalName,
        ];
    }

    /**
     * Retrieves active theme background and foreground colors.
     * @return array{bg: array<int, int>, fg: array<int, int>}
     */
    private static function getThemeColors(): array
    {
        $defaultColors = [
            'bg' => [244, 241, 234], // #F4F1EA
            'fg' => [44, 46, 47],    // #2C2E2F
        ];

        if (class_exists('\\DefaultTheme') && method_exists('\\DefaultTheme', 'getColors')) {
            /** @psalm-suppress UndefinedClass */
            $themeColors = \DefaultTheme::getColors();
            if (isset($themeColors['bg'], $themeColors['fg']) && is_array($themeColors['bg']) && is_array($themeColors['fg'])) {
                return $themeColors;
            }
        }

        return $defaultColors;
    }

    /**
     * Resets in-memory caches (useful in tests).
     */
    public static function reset(): void
    {
        self::$vaultFileIndex = [];
        self::$publishedBasenames = [];
        self::$sourceToPublished = [];
    }
}
