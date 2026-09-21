<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Exception;
use Indieinabox\Core\Database;
use Indieinabox\Services\Contracts\ExportServiceInterface;
use Indieinabox\Services\Contracts\ImportServiceInterface;
use Indieinabox\Site\Site;
use RuntimeException;

/**
 * Service handling portable bundle inspection and atomic import restoration.
 */
class ImportService implements ImportServiceInterface
{
    private Site $site;
    private ExportServiceInterface $exportService;

    public function __construct(?Site $site = null, ?ExportServiceInterface $exportService = null)
    {
        if ($site !== null) {
            $this->site = $site;
        } elseif (isset($GLOBALS['site']) && $GLOBALS['site'] instanceof Site) {
            $this->site = $GLOBALS['site'];
        } else {
            $paths = new \Indieinabox\Site\Paths(Database::$dataDir ?: sys_get_temp_dir());
            $this->site = new Site(null, $paths);
        }

        $this->exportService = $exportService ?? new ExportService($this->site);
    }

    #[\Override]
    public function inspect(string $archivePath): array
    {
        if (!is_file($archivePath) || !is_readable($archivePath)) {
            return [
                'valid' => false,
                'manifest' => [],
                'errors' => ["Archive file not found or not readable: {$archivePath}"],
            ];
        }

        $tempStaging = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iiab_inspect_' . bin2hex(random_bytes(6));
        @mkdir($tempStaging, 0755, true);

        try {
            $this->extractArchive($archivePath, $tempStaging);
            $manifestFile = $tempStaging . DIRECTORY_SEPARATOR . 'manifest.json';
            if (!is_file($manifestFile)) {
                return [
                    'valid' => false,
                    'manifest' => [],
                    'errors' => ['Archive is invalid: missing manifest.json'],
                ];
            }

            $raw = file_get_contents($manifestFile);
            if ($raw === false) {
                return [
                    'valid' => false,
                    'manifest' => [],
                    'errors' => ['Could not read manifest.json from archive'],
                ];
            }

            /** @var array<string, mixed>|null $manifest */
            $manifest = json_decode($raw, true);
            if (!is_array($manifest)) {
                return [
                    'valid' => false,
                    'manifest' => [],
                    'errors' => ['Archive manifest.json is not valid JSON'],
                ];
            }

            $errors = $this->verifyManifestFiles($tempStaging, $manifest);

            return [
                'valid' => empty($errors),
                'manifest' => $manifest,
                'errors' => $errors,
            ];
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'manifest' => [],
                'errors' => [$e->getMessage()],
            ];
        } finally {
            $this->deleteDirectoryRecursive($tempStaging);
        }
    }

    #[\Override]
    public function import(string $archivePath, array $options = []): array
    {
        if (!is_file($archivePath) || !is_readable($archivePath)) {
            throw new RuntimeException("Archive file does not exist or is not readable: {$archivePath}");
        }

        $verifyChecksums = $options['verify_checksums'] ?? true;
        $safetyBackup = $options['safety_backup'] ?? true;
        $restoreDatabase = $options['restore_database'] ?? true;
        $restoreContent = $options['restore_content'] ?? true;
        $restoreMedia = $options['restore_media'] ?? true;
        $rebuildSite = $options['rebuild_site'] ?? true;

        $tempStaging = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'iiab_import_' . bin2hex(random_bytes(6));
        @mkdir($tempStaging, 0755, true);

        $safetyBackupPath = null;
        $filesRestored = 0;

        try {
            $this->extractArchive($archivePath, $tempStaging);

            $manifestFile = $tempStaging . DIRECTORY_SEPARATOR . 'manifest.json';
            if (!is_file($manifestFile)) {
                throw new RuntimeException("Cannot import bundle: missing manifest.json");
            }

            $raw = file_get_contents($manifestFile);
            if ($raw === false) {
                throw new RuntimeException("Cannot read manifest.json from archive");
            }

            /** @var array<string, mixed>|null $manifest */
            $manifest = json_decode($raw, true);
            if (!is_array($manifest)) {
                throw new RuntimeException("Cannot import bundle: corrupted manifest.json");
            }

            if ($verifyChecksums) {
                $errors = $this->verifyManifestFiles($tempStaging, $manifest);
                if (!empty($errors)) {
                    throw new RuntimeException("Integrity check failed: " . implode('; ', $errors));
                }
            }

            // Create pre-import safety backup if requested
            if ($safetyBackup) {
                $baseDir = rtrim($this->site->paths->baseDir, DIRECTORY_SEPARATOR);
                $backupDir = $baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'backups';
                if (!is_dir($backupDir)) {
                    @mkdir($backupDir, 0755, true);
                }
                $safetyBackupFile = $backupDir . DIRECTORY_SEPARATOR . 'pre_import_safety_' . date('Ymd_His') . '.tar.gz';
                $backupRes = $this->exportService->export($safetyBackupFile, [
                    'skip_content' => false,
                    'skip_media' => false,
                ]);
                $safetyBackupPath = $backupRes['path'] ?? null;
            }

            $baseDir = rtrim($this->site->paths->baseDir, DIRECTORY_SEPARATOR);

            // Restore Database
            if ($restoreDatabase) {
                $extractedDb = null;
                $candidates = [
                    $tempStaging . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'indieinabox.sqlite',
                    $tempStaging . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite',
                    $tempStaging . DIRECTORY_SEPARATOR . 'database.sqlite',
                ];
                foreach ($candidates as $cand) {
                    if (is_file($cand)) {
                        $extractedDb = $cand;
                        break;
                    }
                }
                if ($extractedDb === null) {
                    $matches = glob($tempStaging . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . '*.sqlite');
                    if (!empty($matches) && is_file($matches[0])) {
                        $extractedDb = $matches[0];
                    }
                }

                if ($extractedDb !== null && is_file($extractedDb)) {
                    $targetDb = $this->resolveTargetDatabasePath();
                    $targetDbDir = dirname($targetDb);
                    if (!is_dir($targetDbDir)) {
                        @mkdir($targetDbDir, 0755, true);
                    }

                    Database::disconnect();

                    // Clean up existing WAL and SHM files
                    if (file_exists($targetDb . '-wal')) {
                        @unlink($targetDb . '-wal');
                    }
                    if (file_exists($targetDb . '-shm')) {
                        @unlink($targetDb . '-shm');
                    }

                    if (!copy($extractedDb, $targetDb)) {
                        throw new RuntimeException("Failed to copy database from archive to {$targetDb}");
                    }

                    Database::connect($targetDb);
                    $filesRestored++;
                }
            }

            // Restore Content
            if ($restoreContent) {
                $extractedContent = $tempStaging . DIRECTORY_SEPARATOR . 'content';
                if (is_dir($extractedContent)) {
                    $targetContent = $this->site->paths->contentDir;
                    if (!str_starts_with($targetContent, '/')) {
                        $targetContent = $baseDir . DIRECTORY_SEPARATOR . $targetContent;
                    }
                    $restored = $this->copyDirectoryOverwrite($extractedContent, $targetContent);
                    $filesRestored += $restored;
                }
            }

            // Restore Media
            if ($restoreMedia) {
                $extractedMedia = $tempStaging . DIRECTORY_SEPARATOR . 'public_media';
                if (is_dir($extractedMedia)) {
                    $targetMedia = $baseDir . DIRECTORY_SEPARATOR . 'public_media';
                    $restored = $this->copyDirectoryOverwrite($extractedMedia, $targetMedia);
                    $filesRestored += $restored;
                }
            }

            // Rebuild site if requested and SiteBuilder is present
            if ($rebuildSite && class_exists(\Indieinabox\SiteBuilder\SiteBuilder::class)) {
                try {
                    $builder = new \Indieinabox\SiteBuilder\SiteBuilder($this->site);
                    $builder->build();
                } catch (\Throwable) {
                    // Suppress build exceptions if during test or headless mode
                }
            }

            return [
                'success' => true,
                'files_restored' => $filesRestored,
                'safety_backup' => $safetyBackupPath,
                'manifest' => $manifest,
            ];
        } finally {
            $this->deleteDirectoryRecursive($tempStaging);
        }
    }

    /**
     * Resolves the target database path on the active system.
     */
    private function resolveTargetDatabasePath(): string
    {
        $baseDir = rtrim($this->site->paths->baseDir, DIRECTORY_SEPARATOR);
        $dataDir = Database::$dataDir;

        if ($dataDir && is_dir($dataDir)) {
            $candidates = [
                $dataDir . DIRECTORY_SEPARATOR . '.indieinabox.sqlite',
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
            return rtrim($dataDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'database.sqlite';
        }

        return $baseDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'database.sqlite';
    }

    /**
     * Verifies that all files declared in the manifest exist in staging and match SHA-256.
     *
     * @param array<string, mixed> $manifest
     * @return array<string> List of errors (empty if completely valid)
     */
    private function verifyManifestFiles(string $stagingDir, array $manifest): array
    {
        $errors = [];
        $files = $manifest['files'] ?? [];

        if (!is_array($files)) {
            return ['Manifest "files" property is missing or malformed'];
        }

        foreach ($files as $key => $entry) {
            $relPath = '';
            $expectedHash = '';

            if (is_string($key) && is_array($entry)) {
                $relPath = $key;
                $expectedHash = (string) ($entry['sha256'] ?? '');
            } elseif (is_array($entry) && isset($entry['path'], $entry['sha256'])) {
                $relPath = (string) $entry['path'];
                $expectedHash = (string) $entry['sha256'];
            } else {
                $errors[] = 'Malformed file entry in manifest';
                continue;
            }

            if ($expectedHash === '') {
                $errors[] = "Missing sha256 hash in manifest for {$relPath}";
                continue;
            }

            $fullPath = $stagingDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);

            if (!is_file($fullPath)) {
                $errors[] = "Missing bundled file: {$relPath}";
                continue;
            }

            $actualHash = hash_file('sha256', $fullPath);
            if ($actualHash !== $expectedHash) {
                $errors[] = "Integrity violation on {$relPath}: expected {$expectedHash}, got {$actualHash}";
            }
        }

        return $errors;
    }

    /**
     * Extracts an archive (.tar.gz, .tar, .zip) to the target directory.
     */
    private function extractArchive(string $archivePath, string $targetDir): void
    {
        if (str_ends_with($archivePath, '.tar.gz') || str_ends_with($archivePath, '.tgz') || str_ends_with($archivePath, '.tar')) {
            try {
                $phar = new \PharData($archivePath);
                $phar->extractTo($targetDir, null, true);
                return;
            } catch (\Throwable $e) {
                // Fallback to tar CLI if available
                $cmd = sprintf('tar -xf %s -C %s 2>&1', escapeshellarg($archivePath), escapeshellarg($targetDir));
                $output = [];
                $returnVar = 0;
                exec($cmd, $output, $returnVar);
                if ($returnVar !== 0) {
                    throw new RuntimeException("Failed to extract tar archive: " . $e->getMessage() . " (" . implode(' ', $output) . ")");
                }
                return;
            }
        }

        if (str_ends_with($archivePath, '.zip')) {
            if (class_exists(\ZipArchive::class)) {
                $zip = new \ZipArchive();
                $res = $zip->open($archivePath);
                if ($res === true) {
                    $zip->extractTo($targetDir);
                    $zip->close();
                    return;
                }
            }

            // Fallback to unzip command
            $cmd = sprintf('unzip -q %s -d %s 2>&1', escapeshellarg($archivePath), escapeshellarg($targetDir));
            $output = [];
            $returnVar = 0;
            exec($cmd, $output, $returnVar);
            if ($returnVar !== 0) {
                throw new RuntimeException("Failed to extract zip archive via unzip command.");
            }
            return;
        }

        throw new RuntimeException("Unsupported archive format. Must be .tar.gz, .tar, or .zip");
    }

    /**
     * Recursively copies source directory contents to destination overwriting existing files.
     *
     * @return int Number of files copied
     */
    private function copyDirectoryOverwrite(string $source, string $target): int
    {
        if (!is_dir($target)) {
            @mkdir($target, 0755, true);
        }

        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $subPath = $iterator->getSubPathname();
            $destPath = $target . DIRECTORY_SEPARATOR . $subPath;

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    @mkdir($destPath, 0755, true);
                }
            } elseif ($item->isFile()) {
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0755, true);
                }
                copy($item->getPathname(), $destPath);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Recursively deletes a directory and its contents.
     */
    private function deleteDirectoryRecursive(string $dir): void
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
