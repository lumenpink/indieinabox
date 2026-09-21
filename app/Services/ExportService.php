<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Core\Database;
use Indieinabox\Core\Version;
use Indieinabox\Services\Contracts\ExportServiceInterface;
use Indieinabox\Site\Site;
use Phar;
use PharData;
use RuntimeException;

/**
 * Service responsible for creating portable, self-contained export archives
 * containing database, content files, media, and an integrity manifest.
 */
class ExportService implements ExportServiceInterface
{
    private Site $site;

    public function __construct(?Site $site = null)
    {
        if ($site !== null) {
            $this->site = $site;
        } elseif (isset($GLOBALS['site']) && $GLOBALS['site'] instanceof Site) {
            $this->site = $GLOBALS['site'];
        } else {
            $paths = new \Indieinabox\Site\Paths(Database::$dataDir ?: sys_get_temp_dir());
            $this->site = new Site(null, $paths);
        }
    }

    /**
     * Creates an export bundle containing database, content, media, and manifest.
     *
     * @param string|null $outputPath Destination file path (.tar.gz or .zip).
     * @param array<string, mixed> $options Export options (skip_media, skip_content).
     * @return array{success: bool, path: string, size_bytes: int, manifest: array<string, mixed>, error?: string}
     */
    #[\Override]
    public function export(?string $outputPath = null, array $options = []): array
    {
        $baseDir = rtrim($this->site->paths->baseDir, DIRECTORY_SEPARATOR);
        $dataDir = Database::$dataDir !== '' ? Database::$dataDir : ($baseDir . DIRECTORY_SEPARATOR . 'data');
        $skipMedia = !empty($options['skip_media']);
        $skipContent = !empty($options['skip_content']);

        if ($outputPath === null || $outputPath === '') {
            $exportDir = $dataDir . DIRECTORY_SEPARATOR . 'exports';
            if (!is_dir($exportDir)) {
                @mkdir($exportDir, 0755, true);
            }
            $outputPath = $exportDir . DIRECTORY_SEPARATOR . 'indieinabox_export_' . date('Y-m-d_His') . '.tar.gz';
        }

        $tempStaging = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iiab_export_' . uniqid('', true);
        if (!mkdir($tempStaging, 0755, true) && !is_dir($tempStaging)) {
            return [
                'success' => false,
                'path' => '',
                'size_bytes' => 0,
                'manifest' => [],
                'error' => "Failed to create temporary staging directory: {$tempStaging}",
            ];
        }

        try {
            $manifest = [
                'format' => 'indieinabox_portable_export',
                'format_version' => 1,
                'indieinabox_version' => Version::get(),
                'created_at' => date('c'),
                'sitename' => (string) ($this->site->metadata->sitename ?? 'Indie In A Box'),
                'fqdn' => (string) ($this->site->metadata->fqdn ?? ''),
                'files' => [],
            ];

            // 1. Export SQLite database
            $dbPath = $this->resolveDatabasePath($dataDir);
            if ($dbPath !== null && is_file($dbPath)) {
                $dbStaging = $tempStaging . DIRECTORY_SEPARATOR . 'database';
                @mkdir($dbStaging, 0755, true);
                $destDb = $dbStaging . DIRECTORY_SEPARATOR . 'indieinabox.sqlite';
                copy($dbPath, $destDb);
                $manifest['files']['database/indieinabox.sqlite'] = [
                    'size' => filesize($destDb),
                    'sha256' => hash_file('sha256', $destDb),
                ];
            }

            // 2. Export Content files
            if (!$skipContent) {
                $contentDir = $this->site->paths->contentDir;
                $fullContentDir = $baseDir . DIRECTORY_SEPARATOR . $contentDir;
                if (is_dir($fullContentDir)) {
                    $this->copyDirectoryRecursive($fullContentDir, $tempStaging . DIRECTORY_SEPARATOR . 'content', $manifest, 'content');
                }
            }

            // 3. Export Public Media
            if (!$skipMedia) {
                $fullMediaDir = $baseDir . DIRECTORY_SEPARATOR . 'public_media';
                if (is_dir($fullMediaDir)) {
                    $this->copyDirectoryRecursive($fullMediaDir, $tempStaging . DIRECTORY_SEPARATOR . 'public_media', $manifest, 'public_media');
                }
            }

            // 4. Write manifest.json
            $manifestJsonPath = $tempStaging . DIRECTORY_SEPARATOR . 'manifest.json';
            file_put_contents($manifestJsonPath, (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            // 5. Create archive
            $this->createArchiveFromDirectory($tempStaging, $outputPath);

            $size = file_exists($outputPath) ? (int) filesize($outputPath) : 0;

            return [
                'success' => true,
                'path' => $outputPath,
                'size_bytes' => $size,
                'manifest' => $manifest,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'path' => '',
                'size_bytes' => 0,
                'manifest' => [],
                'error' => $e->getMessage(),
            ];
        } finally {
            $this->removeDirectoryRecursive($tempStaging);
        }
    }

    /**
     * Resolves the primary SQLite database file path.
     */
    private function resolveDatabasePath(string $dataDir): ?string
    {
        $candidates = [
            $dataDir . DIRECTORY_SEPARATOR . '.indieinabox.sqlite',
            $dataDir . DIRECTORY_SEPARATOR . 'indieinabox.sqlite',
            $dataDir . DIRECTORY_SEPARATOR . 'database.sqlite',
        ];

        foreach ($candidates as $cand) {
            if (is_file($cand)) {
                return $cand;
            }
        }

        $matches = glob($dataDir . DIRECTORY_SEPARATOR . '*.sqlite');
        if (!empty($matches) && is_file($matches[0])) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Recursively copies a source directory to a target directory and records entries in the manifest.
     *
     * @param array<string, mixed> $manifest
     */
    private function copyDirectoryRecursive(string $source, string $target, array &$manifest, string $prefix): void
    {
        if (!is_dir($target)) {
            @mkdir($target, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $subPathName = $iterator->getSubPathname();
            $destPath = $target . DIRECTORY_SEPARATOR . $subPathName;
            $relativeManifestKey = $prefix . '/' . str_replace('\\', '/', $subPathName);

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    @mkdir($destPath, 0755, true);
                }
            } elseif ($item->isFile()) {
                $itemDir = dirname($destPath);
                if (!is_dir($itemDir)) {
                    @mkdir($itemDir, 0755, true);
                }
                copy($item->getPathname(), $destPath);
                $manifest['files'][$relativeManifestKey] = [
                    'size' => $item->getSize(),
                    'sha256' => hash_file('sha256', $item->getPathname()),
                ];
            }
        }
    }

    /**
     * Packages a directory into an archive file (.tar.gz or .zip).
     */
    private function createArchiveFromDirectory(string $sourceDir, string $destinationFile): void
    {
        $outDir = dirname($destinationFile);
        if (!is_dir($outDir)) {
            @mkdir($outDir, 0755, true);
        }

        // Handle .tar.gz or .tar
        if (str_ends_with($destinationFile, '.tar.gz') || str_ends_with($destinationFile, '.tgz') || str_ends_with($destinationFile, '.tar')) {
            $tarPath = str_ends_with($destinationFile, '.tar') ? $destinationFile : preg_replace('/(\.tar\.gz|\.tgz)$/', '.tar', $destinationFile);
            if ($tarPath === null) {
                $tarPath = $destinationFile . '.tar';
            }

            if (file_exists($tarPath)) {
                @unlink($tarPath);
            }
            if (file_exists($destinationFile)) {
                @unlink($destinationFile);
            }

            $phar = new PharData($tarPath);
            $phar->buildFromDirectory($sourceDir);

            if (str_ends_with($destinationFile, '.tar.gz') || str_ends_with($destinationFile, '.tgz')) {
                $phar->compress(Phar::GZ);
                if (file_exists($tarPath)) {
                    @unlink($tarPath);
                }
            }
            return;
        }

        // Handle .zip using PharData or system zip
        if (str_ends_with($destinationFile, '.zip')) {
            if (file_exists($destinationFile)) {
                @unlink($destinationFile);
            }
            try {
                $phar = new PharData($destinationFile);
                $phar->buildFromDirectory($sourceDir);
                return;
            } catch (\Throwable) {
                // Fallback to tar command if available, or force .tar.gz
                $escapedDest = escapeshellarg($destinationFile);
                $escapedSrc = escapeshellarg($sourceDir);
                exec("cd {$escapedSrc} && zip -r {$escapedDest} . 2>&1", $out, $ret);
                if ($ret === 0 && file_exists($destinationFile)) {
                    return;
                }
                throw new RuntimeException("Cannot create ZIP archive: neither Phar ZIP nor system zip available.");
            }
        }

        // Fallback default: create .tar.gz
        $tarPath = $destinationFile . '.tar';
        $phar = new PharData($tarPath);
        $phar->buildFromDirectory($sourceDir);
        $phar->compress(Phar::GZ);
        if (file_exists($tarPath)) {
            @unlink($tarPath);
        }
    }

    /**
     * Recursively deletes a directory.
     */
    private function removeDirectoryRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($dir);
    }
}
