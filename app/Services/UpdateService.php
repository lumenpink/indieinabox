<?php
declare(strict_types=1);

namespace Indieinabox\Services;

use Exception;
use Indieinabox\Core\Database;
use Indieinabox\Core\Version;

class UpdateService
{
    public const API_URL = 'https://codeberg.org/api/v1/repos/indieinabox/indieinabox/releases';
    public const MAX_BACKUPS = 2;

    /**
     * Optional override for the executable path (used for testing and custom runners).
     */
    public static ?string $customExecutablePath = null;

    /**
     * Optional override for versions directory (used for testing).
     */
    public static ?string $customVersionsDir = null;

    /**
     * Returns the directory where version backups are stored.
     *
     * @return string
     */
    public static function getVersionsDir(): string
    {
        if (self::$customVersionsDir !== null) {
            if (!is_dir(self::$customVersionsDir)) {
                @mkdir(self::$customVersionsDir, 0755, true);
            }
            return self::$customVersionsDir;
        }

        $dir = !empty(Database::$dataDir)
            ? Database::$dataDir . '/versions'
            : dirname(__DIR__, 2) . '/data/versions';

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /**
     * Checks Codeberg API for available releases and caches them in the database.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function checkAvailableVersions(): array
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
                return self::getAvailableUpdates();
            }

            $releases = json_decode($response, true);
            if (!is_array($releases)) {
                return self::getAvailableUpdates();
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

            Database::saveSetting('available_updates', $available);
            Database::saveSetting('last_update_check', time());

            return $available;
        } catch (Exception $e) {
            error_log("Updater check exception: " . $e->getMessage());
            return self::getAvailableUpdates();
        }
    }

    /**
     * Retrieves currently cached available updates.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAvailableUpdates(): array
    {
        $cached = Database::getSetting('available_updates', []);
        return is_array($cached) ? $cached : [];
    }

    /**
     * Finds the latest release matching release preference.
     *
     * @param bool $includePrerelease
     * @return array<string, mixed>|null
     */
    public static function getLatestRelease(bool $includePrerelease = false): ?array
    {
        $updates = self::getAvailableUpdates();
        if (empty($updates)) {
            $updates = self::checkAvailableVersions();
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

    /**
     * Downloads and installs an update from the specified URL.
     * Performs a backup before overwriting.
     *
     * @param string $downloadUrl
     * @return bool
     */
    public static function downloadAndInstall(string $downloadUrl): bool
    {
        $targetFile = self::getCurrentExecutablePath();

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

        self::backupCurrentVersion();

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

    /**
     * Backs up the current executable, retaining only the MAX_BACKUPS most recent backups.
     *
     * @return string|false Path to the created backup file, or false if backup failed.
     */
    public static function backupCurrentVersion(): string|false
    {
        $versionsDir = self::getVersionsDir();
        $targetFile = self::getCurrentExecutablePath();

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

        self::cleanupOldBackups();

        return $backupPath;
    }

    /**
     * Rolls back to a previous backup.
     *
     * @param string|null $backupFilename Filename of backup to restore. If null, restores the latest backup.
     * @return bool
     */
    public static function rollback(?string $backupFilename = null): bool
    {
        $versionsDir = self::getVersionsDir();
        $backups = self::getLocalBackups();

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

        $targetFile = self::getCurrentExecutablePath();

        // Backup current executable before restoring
        self::backupCurrentVersion();

        if (file_put_contents($targetFile, $backupContent) === false) {
            return false;
        }

        @chmod($targetFile, 0755);
        return true;
    }

    /**
     * Retrieves the list of local backup files, sorted with newest first.
     *
     * @return array<int, array{filename: string, path: string, version: ?string, date: int, formatted_date: string, size: int}>
     */
    public static function getLocalBackups(): array
    {
        $versionsDir = self::getVersionsDir();
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

    /**
     * Enforces the MAX_BACKUPS limit by deleting the oldest backups.
     *
     * @return int Number of deleted backup files.
     */
    public static function cleanupOldBackups(): int
    {
        $backups = self::getLocalBackups();
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

    /**
     * Executes scheduled update checking and auto-upgrade logic for CRON.
     *
     * @param int $intervalSeconds Interval in seconds between checks (default: 21600 = 6 hours).
     * @return array{checked: bool, upgraded: bool, message: string}
     */
    public static function processScheduledUpdate(int $intervalSeconds = 21600): array
    {
        $lastCheck = (int) Database::getSetting('last_update_check', 0);
        $result = [
            'checked' => false,
            'upgraded' => false,
            'message' => 'Skipping update check (last check was recent).',
        ];

        if ((time() - $lastCheck) <= $intervalSeconds) {
            return $result;
        }

        $available = self::checkAvailableVersions();
        $result['checked'] = true;
        $result['message'] = 'Update check performed.';

        $autoUpgradeNightly = (bool) Database::getSetting('auto_upgrade_nightly', false);
        $autoUpgradeStable = (bool) Database::getSetting('auto_upgrade_stable', false);

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
            $lastInstalled = Database::getSetting('last_installed_update_id', null);
            if (($target['id'] ?? null) !== $lastInstalled) {
                $success = self::downloadAndInstall((string) $target['download_url']);
                if ($success) {
                    Database::saveSetting('last_installed_update_id', $target['id'] ?? null);
                    $result['upgraded'] = true;
                    $result['message'] = 'Auto-upgraded to ' . ($target['name'] ?? 'latest release');
                } else {
                    $result['message'] = 'Auto-upgrade failed to install ' . ($target['name'] ?? 'release');
                }
            }
        }

        return $result;
    }

    /**
     * Determines the path of the current main executable (indieinabox.php, index.php, or build.php).
     *
     * @return string
     */
    public static function getCurrentExecutablePath(): string
    {
        if (self::$customExecutablePath !== null) {
            return self::$customExecutablePath;
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
