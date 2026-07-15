<?php
declare(strict_types=1);

namespace Indieinabox;

use Exception;

class Updater
{
    private const API_URL = 'https://codeberg.org/api/v1/repos/indieinabox/indieinabox/releases';
    private const VERSIONS_DIR = __DIR__ . '/../data/versions';
    private const MAX_BACKUPS = 5;

    public static function checkAvailableVersions(): void
    {
        try {
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Indieinabox-Updater/1.0',
                    'timeout' => 10,
                ]
            ];
            $context = stream_context_create($options);
            $response = @file_get_contents(self::API_URL, false, $context);

            if ($response === false) {
                error_log("Updater: Failed to fetch releases from Codeberg.");
                return;
            }

            $releases = json_decode($response, true);
            if (!is_array($releases)) {
                return;
            }

            $available = [];
            foreach ($releases as $release) {
                if (empty($release['assets'])) continue;
                
                // Find the index.php asset
                $assetUrl = null;
                foreach ($release['assets'] as $asset) {
                    if ($asset['name'] === 'index.php') {
                        $assetUrl = $asset['browser_download_url'];
                        break;
                    }
                }
                
                if ($assetUrl) {
                    $available[] = [
                        'id' => $release['id'],
                        'name' => $release['name'],
                        'tag_name' => $release['tag_name'],
                        'prerelease' => $release['prerelease'],
                        'published_at' => $release['published_at'],
                        'download_url' => $assetUrl
                    ];
                }
            }

            Database::saveSetting('available_updates', $available);
            Database::saveSetting('last_update_check', time());

        } catch (Exception $e) {
            error_log("Updater check exception: " . $e->getMessage());
        }
    }

    public static function downloadAndInstall(string $downloadUrl): bool
    {
        $targetFile = self::getCurrentExecutablePath();
        
        // Download to temp file first
        $tempFile = sys_get_temp_dir() . '/indieinabox_update_' . uniqid() . '.php';
        
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: Indieinabox-Updater/1.0',
                'timeout' => 30,
            ]
        ];
        $context = stream_context_create($options);
        $content = @file_get_contents($downloadUrl, false, $context);
        
        if ($content === false || empty($content)) {
            error_log("Updater: Failed to download update from $downloadUrl");
            return false;
        }
        
        if (file_put_contents($tempFile, $content) === false) {
            error_log("Updater: Failed to write to temp file.");
            return false;
        }
        
        // Basic validation: check if it's a valid PHP file starting with <?php
        if (strpos(trim($content), '<?php') !== 0) {
            error_log("Updater: Downloaded file is not a valid PHP script.");
            @unlink($tempFile);
            return false;
        }

        // Backup current
        self::backupCurrentVersion();

        // Overwrite target file
        if (!@rename($tempFile, $targetFile)) {
            // Fallback to copy/unlink if rename across volumes fails
            if (copy($tempFile, $targetFile)) {
                unlink($tempFile);
            } else {
                error_log("Updater: Failed to overwrite target executable.");
                @unlink($tempFile);
                return false;
            }
        }
        
        @chmod($targetFile, 0755);
        return true;
    }

    public static function backupCurrentVersion(): void
    {
        if (!is_dir(self::VERSIONS_DIR)) {
            mkdir(self::VERSIONS_DIR, 0777, true);
        }

        $targetFile = self::getCurrentExecutablePath();
        if (file_exists($targetFile)) {
            $datePrefix = date('Ymd_His');
            $backupFile = self::VERSIONS_DIR . "/indieinabox_backup_{$datePrefix}.php";
            copy($targetFile, $backupFile);
        }

        self::cleanupOldBackups();
    }

    public static function rollback(string $backupFilename): bool
    {
        $backupPath = self::VERSIONS_DIR . '/' . basename($backupFilename);
        if (!file_exists($backupPath)) {
            return false;
        }

        $targetFile = self::getCurrentExecutablePath();
        
        // Take a backup of the current state before rolling back, just in case
        self::backupCurrentVersion();
        
        if (!@rename($backupPath, $targetFile)) {
             if (copy($backupPath, $targetFile)) {
                 unlink($backupPath);
             } else {
                 return false;
             }
        }
        @chmod($targetFile, 0755);
        return true;
    }

    public static function getLocalBackups(): array
    {
        if (!is_dir(self::VERSIONS_DIR)) {
            return [];
        }

        $files = glob(self::VERSIONS_DIR . '/indieinabox_backup_*.php');
        $backups = [];
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'date' => filemtime($file),
                'size' => filesize($file)
            ];
        }

        usort($backups, fn($a, $b) => $b['date'] <=> $a['date']);
        return $backups;
    }

    private static function cleanupOldBackups(): void
    {
        $backups = self::getLocalBackups();
        if (count($backups) > self::MAX_BACKUPS) {
            $toDelete = array_slice($backups, self::MAX_BACKUPS);
            foreach ($toDelete as $backup) {
                @unlink(self::VERSIONS_DIR . '/' . $backup['filename']);
            }
        }
    }

    /**
     * Determines the path of the current main executable (index.php or indieinabox.php).
     */
    private static function getCurrentExecutablePath(): string
    {
        if (isset($_SERVER['SCRIPT_FILENAME']) && file_exists($_SERVER['SCRIPT_FILENAME'])) {
            $basename = basename($_SERVER['SCRIPT_FILENAME']);
            if (in_array($basename, ['index.php', 'indieinabox.php'])) {
                return realpath($_SERVER['SCRIPT_FILENAME']);
            }
        }
        
        $baseDir = dirname(__DIR__);
        if (file_exists($baseDir . '/index.php')) {
            return $baseDir . '/index.php';
        }
        if (file_exists($baseDir . '/indieinabox.php')) {
            return $baseDir . '/indieinabox.php';
        }
        
        return $baseDir . '/indieinabox.php';
    }
}
