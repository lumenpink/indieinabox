<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Core\Database;
use Indieinabox\Core\Version;
use Indieinabox\Services\UpdateService;

/**
 * Command to check, apply, inspect backups, and rollback application versions via CLI.
 */
class UpdateCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'update';
    }

    public function getDescription(): string
    {
        return 'Checks for updates, upgrades the single-file binary, or rolls back to previous versions.';
    }

    public function getUsage(): string
    {
        return "Usage: php indieinabox.php update [command]\n" .
               "Commands:\n" .
               "  --check       Check for available updates (default)\n" .
               "  --apply       Download and install the latest stable update\n" .
               "  --backups     List local backup archives\n" .
               "  --rollback    Rollback to the latest backup or --file <filename>";
    }

    public function execute(array $argv): int
    {
        $action = $argv[2] ?? '--check';
        if ($action === 'check' || $action === '--check') {
            echo "Checking Codeberg releases for updates...\n";
            $releases = UpdateService::checkAvailableVersions();
            $current = Version::get();
            echo "Current version: {$current}\n";

            if (empty($releases)) {
                echo "No remote releases found or unable to connect to Codeberg.\n";
                return 0;
            }

            $latest = $releases[0];
            echo "Latest available release: " . ($latest['name'] ?? $latest['tag_name']) . " (" . $latest['tag_name'] . ")\n";
            echo "Download URL: " . ($latest['download_url'] ?? 'N/A') . "\n";

            $cleanCurrent = preg_replace('/^v/', '', Version::getBaseVersion());
            $cleanTag = preg_replace('/^v/', '', (string) $latest['tag_name']);
            if (version_compare((string) $cleanTag, (string) $cleanCurrent, '>')) {
                echo "\nA newer version is available! Run 'php build.php update --apply' to install it.\n";
            } else {
                echo "\nYou are already on the latest version.\n";
            }
            return 0;
        }

        if ($action === 'apply' || $action === '--apply' || $action === 'install') {
            echo "Fetching latest release information...\n";
            $latest = UpdateService::getLatestRelease(false);
            if (!$latest || empty($latest['download_url'])) {
                echo "Error: No downloadable release found.\n";
                return 1;
            }

            echo "Backing up current executable and installing " . ($latest['name'] ?? $latest['tag_name']) . "...\n";
            $success = UpdateService::downloadAndInstall((string) $latest['download_url']);
            if ($success) {
                Database::saveSetting('last_installed_update_id', $latest['id'] ?? null);
                echo "Success: Update installed successfully!\n";
                return 0;
            }

            echo "Error: Failed to download or install the update.\n";
            return 1;
        }

        if ($action === 'backups' || $action === '--backups' || $action === 'list') {
            $backups = UpdateService::getLocalBackups();
            echo "Local version backups (retaining up to " . UpdateService::MAX_BACKUPS . "):\n\n";
            if (empty($backups)) {
                echo "No backups found.\n";
                return 0;
            }

            foreach ($backups as $index => $b) {
                $num = $index + 1;
                $ver = $b['version'] ? "v" . $b['version'] : "version unknown";
                $sizeKb = round($b['size'] / 1024, 1);
                echo "  {$num}. {$b['filename']} ({$ver}, {$b['formatted_date']}, {$sizeKb} KB)\n";
            }
            return 0;
        }

        if ($action === 'rollback' || $action === '--rollback') {
            $target = $this->getOption($argv, 'file') ?? ($argv[3] ?? null);
            if ($target !== null && str_starts_with($target, '--')) {
                $target = null;
            }

            $backups = UpdateService::getLocalBackups();
            if (empty($backups)) {
                echo "Error: No backups available for rollback.\n";
                return 1;
            }

            $targetFilename = $target ?? $backups[0]['filename'];
            echo "Rolling back to backup: {$targetFilename}...\n";
            $success = UpdateService::rollback($targetFilename);
            if ($success) {
                echo "Success: Rollback completed successfully!\n";
                return 0;
            }

            echo "Error: Rollback failed. Please check file permissions and backup existence.\n";
            return 1;
        }

        echo $this->getUsage() . "\n";
        return 1;
    }
}
