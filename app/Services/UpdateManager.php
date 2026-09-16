<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Exception;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Core\Version;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Repositories\SqliteSettingsRepository;
use Indieinabox\Services\Contracts\UpdateServiceInterface;

/**
 * Service managing application updates, release downloads, and binary backups.
 */
class UpdateManager implements UpdateServiceInterface
{
    private SettingsRepositoryInterface $settingsRepo;

    public function __construct(?SettingsRepositoryInterface $settingsRepo = null)
    {
        if ($settingsRepo !== null) {
            $this->settingsRepo = $settingsRepo;
        } elseif (class_exists(Container::class) && Container::getInstance()->has(SettingsRepositoryInterface::class)) {
            $this->settingsRepo = Container::getInstance()->get(SettingsRepositoryInterface::class);
        } else {
            $this->settingsRepo = new SqliteSettingsRepository();
        }
    }

    public function getVersionsDir(): string
    {
        if (UpdateService::$customVersionsDir !== null) {
            if (!is_dir(UpdateService::$customVersionsDir)) {
                @mkdir(UpdateService::$customVersionsDir, 0755, true);
            }
            return UpdateService::$customVersionsDir;
        }

        $dir = !empty(Database::$dataDir)
            ? Database::$dataDir . '/versions'
            : dirname(__DIR__, 2) . '/data/versions';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }

    public function checkAvailableVersions(): array
    {
        try {
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Indieinabox-Updater/' . Version::getBaseVersion(),
                    'timeout' => 10,
                ]
            ];
            $context = stream_context_create($options);
            $response = @file_get_contents(self::API_URL, false, $context);

            if ($response === false) {
                error_log("Updater: Failed to fetch releases from Codeberg.");
                return $this->getAvailableUpdates();
            }

            $releases = json_decode($response, true);
            if (!is_array($releases)) {
                return $this->getAvailableUpdates();
            }

            $available = [];
            foreach ($releases as $release) {
                if (empty($release['assets']) || !is_array($release['assets'])) {
                    continue;
                }

                $assetUrl = null;
                foreach ($release['assets'] as $asset) {
                    if (($asset['name'] ?? '') === 'indieinabox.php') {
                        $assetUrl = $asset['browser_download_url'] ?? null;
                        break;
                    }
                }

                if ($assetUrl !== null) {
                    $available[] = [
                        'id' => $release['id'] ?? null,
                        'name' => $release['name'] ?? ($release['tag_name'] ?? 'Release'),
                        'tag_name' => $release['tag_name'] ?? '',
                        'prerelease' => (bool) ($release['prerelease'] ?? false),
                        'published_at' => $release['published_at'] ?? '',
                        'download_url' => $assetUrl,
                    ];
                }
            }

            $this->settingsRepo->set('available_updates', $available);
            $this->settingsRepo->set('last_update_check', time());

            return $available;
        } catch (Exception $e) {
            error_log("Updater check exception: " . $e->getMessage());
            return $this->getAvailableUpdates();
        }
    }

    public function getAvailableUpdates(): array
    {
        $cached = $this->settingsRepo->get('available_updates', []);
        return is_array($cached) ? $cached : [];
    }

    public function getLatestRelease(bool $includePrerelease = false): ?array
    {
        $updates = $this->getAvailableUpdates();
        if (empty($updates)) {
            $updates = $this->checkAvailableVersions();
        }

        foreach ($updates as $release) {
            $isPre = (bool) ($release['prerelease'] ?? false);
            if ($isPre && !$includePrerelease) {
                continue;
            }
            return $release;
        }

        return null;
    }

    public function downloadAndInstall(string $downloadUrl): bool
    {
        $targetFile = $this->getCurrentExecutablePath();

        $tempFile = sys_get_temp_dir() . '/indieinabox_update_' . uniqid('', true) . '.php';

        $options = [
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: Indieinabox-Updater/' . Version::getBaseVersion(),
                'timeout' => 30,
            ]
        ];
        $context = stream_context_create($options);
        $content = @file_get_contents($downloadUrl, false, $context);

        if ($content === false || empty($content)) {
            error_log("Updater: Failed to download update from {$downloadUrl}");
            return false;
        }

        if (file_put_contents($tempFile, $content) === false) {
            error_log("Updater: Failed to write to temp file.");
            return false;
        }

        if (strpos(trim($content), '<?php') !== 0) {
            error_log("Updater: Downloaded file is not a valid PHP script.");
            @unlink($tempFile);
            return false;
        }

        $this->backupCurrentVersion();

        if (!@rename($tempFile, $targetFile)) {
            if (copy($tempFile, $targetFile)) {
                @unlink($tempFile);
            } else {
                error_log("Updater: Failed to overwrite target executable {$targetFile}.");
                @unlink($tempFile);
                return false;
            }
        }

        @chmod($targetFile, 0755);
        return true;
    }

    public function backupCurrentVersion(): string|false
    {
        $versionsDir = $this->getVersionsDir();
        $targetFile = $this->getCurrentExecutablePath();

        if (!file_exists($targetFile)) {
            return false;
        }

        $rawVersion = Version::get();
        $cleanVersion = preg_replace('/[^a-zA-Z0-9\.\-]/', '_', $rawVersion) ?: 'unknown';
        $timestamp = date('Ymd_His');
        $backupFilename = "indieinabox_backup_v{$cleanVersion}_{$timestamp}.php";
        $backupPath = $versionsDir . '/' . $backupFilename;

        if (!copy($targetFile, $backupPath)) {
            return false;
        }

        $this->cleanupOldBackups();

        return $backupPath;
    }

    public function rollback(?string $backupFilename = null): bool
    {
        $versionsDir = $this->getVersionsDir();
        $backups = $this->getLocalBackups();

        if (empty($backups)) {
            return false;
        }

        $targetBackup = ($backupFilename !== null && trim($backupFilename) !== '')
            ? basename($backupFilename)
            : $backups[0]['filename'];

        $backupPath = $versionsDir . '/' . $targetBackup;
        if (!file_exists($backupPath)) {
            return false;
        }

        $backupContent = @file_get_contents($backupPath);
        if ($backupContent === false || trim($backupContent) === '') {
            return false;
        }

        $targetFile = $this->getCurrentExecutablePath();

        // Backup current executable before restoring
        $this->backupCurrentVersion();

        if (file_put_contents($targetFile, $backupContent) === false) {
            return false;
        }

        @chmod($targetFile, 0755);
        return true;
    }

    public function getLocalBackups(): array
    {
        $versionsDir = $this->getVersionsDir();
        if (!is_dir($versionsDir)) {
            return [];
        }

        $files = glob($versionsDir . '/indieinabox_backup_*.php');
        if (!$files) {
            return [];
        }

        $backups = [];
        foreach ($files as $file) {
            $filename = basename($file);
            $version = null;
            if (preg_match('/^indieinabox_backup_v([^_]+)_/i', $filename, $matches)) {
                $version = $matches[1];
            }

            $mtime = (int) @filemtime($file);
            $backups[] = [
                'filename' => $filename,
                'path' => $file,
                'version' => $version,
                'date' => $mtime,
                'formatted_date' => date('Y-m-d H:i:s', $mtime),
                'size' => (int) @filesize($file),
            ];
        }

        usort($backups, fn($a, $b) => $b['date'] <=> $a['date']);

        return $backups;
    }

    public function cleanupOldBackups(): int
    {
        $backups = $this->getLocalBackups();
        $deleted = 0;

        if (count($backups) > self::MAX_BACKUPS) {
            $toDelete = array_slice($backups, self::MAX_BACKUPS);
            foreach ($toDelete as $backup) {
                if (@unlink($backup['path'])) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    public function processScheduledUpdate(int $intervalSeconds = 21600): array
    {
        $lastCheck = (int) $this->settingsRepo->get('last_update_check', 0);
        $result = [
            'checked' => false,
            'upgraded' => false,
            'message' => 'Skipping update check (last check was recent).',
        ];

        if ((time() - $lastCheck) <= $intervalSeconds) {
            return $result;
        }

        $available = $this->checkAvailableVersions();
        $result['checked'] = true;
        $result['message'] = 'Update check performed.';

        $autoUpgradeNightly = (bool) $this->settingsRepo->get('auto_upgrade_nightly', false);
        $autoUpgradeStable = (bool) $this->settingsRepo->get('auto_upgrade_stable', false);

        if (!$autoUpgradeNightly && !$autoUpgradeStable) {
            return $result;
        }

        $target = null;
        foreach ($available as $release) {
            if (!empty($release['prerelease']) && $autoUpgradeNightly) {
                $target = $release;
                break;
            }
            if (empty($release['prerelease']) && $autoUpgradeStable) {
                $target = $release;
                break;
            }
        }

        if ($target !== null && !empty($target['download_url'])) {
            $lastInstalled = $this->settingsRepo->get('last_installed_update_id', null);
            if (($target['id'] ?? null) !== $lastInstalled) {
                $success = $this->downloadAndInstall((string) $target['download_url']);
                if ($success) {
                    $this->settingsRepo->set('last_installed_update_id', $target['id'] ?? null);
                    $result['upgraded'] = true;
                    $result['message'] = 'Auto-upgraded to ' . ($target['name'] ?? 'latest release');
                } else {
                    $result['message'] = 'Auto-upgrade failed to install ' . ($target['name'] ?? 'release');
                }
            }
        }

        return $result;
    }

    public function getCurrentExecutablePath(): string
    {
        if (UpdateService::$customExecutablePath !== null) {
            return UpdateService::$customExecutablePath;
        }

        if (isset($_SERVER['SCRIPT_FILENAME']) && file_exists($_SERVER['SCRIPT_FILENAME'])) {
            $basename = basename($_SERVER['SCRIPT_FILENAME']);
            if (in_array($basename, ['indieinabox.php', 'index.php', 'build.php'], true)) {
                $real = realpath($_SERVER['SCRIPT_FILENAME']);
                if ($real !== false) {
                    return $real;
                }
            }
        }

        $baseDir = dirname(__DIR__, 2);
        if (file_exists($baseDir . '/indieinabox.php')) {
            return $baseDir . '/indieinabox.php';
        }
        if (file_exists($baseDir . '/index.php')) {
            return $baseDir . '/index.php';
        }

        return $baseDir . '/indieinabox.php';
    }
}
