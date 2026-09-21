<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Services\ImportService;

/**
 * Command to inspect and import a portable export bundle.
 */
class ImportCommand extends AbstractCommand
{
    #[\Override]
    public function getName(): string
    {
        return 'import';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Imports a portable export bundle with checksum verification and safety backup.';
    }

    #[\Override]
    public function getUsage(): string
    {
        return 'php indieinabox.php import <archive-file> [--inspect] [--no-verify] [--no-safety-backup] [--no-content] [--no-media] [--no-build]';
    }

    #[\Override]
    public function getAliases(): array
    {
        return ['backup:import'];
    }

    #[\Override]
    public function execute(array $argv): int
    {
        $inspectOnly = $this->hasFlag($argv, '--inspect');
        $noVerify = $this->hasFlag($argv, '--no-verify');
        $noSafetyBackup = $this->hasFlag($argv, '--no-safety-backup');
        $noContent = $this->hasFlag($argv, '--no-content');
        $noMedia = $this->hasFlag($argv, '--no-media');
        $noBuild = $this->hasFlag($argv, '--no-build');

        // Look for positional archive file or --file option
        $archivePath = $this->getOption($argv, 'file');
        if ($archivePath === null) {
            foreach ($argv as $arg) {
                if (!str_starts_with($arg, '-') && !in_array($arg, ['import', 'backup:import'], true) && !str_ends_with($arg, 'indieinabox.php')) {
                    $archivePath = $arg;
                    break;
                }
            }
        }

        if ($archivePath === null || $archivePath === '') {
            echo "Error: Missing required archive file path.\n";
            echo "Usage: " . $this->getUsage() . "\n";
            return 1;
        }

        $importService = new ImportService($this->site);

        if ($inspectOnly) {
            echo "Inspecting portable export bundle: {$archivePath}...\n";
            $inspection = $importService->inspect($archivePath);

            if (!$inspection['valid']) {
                echo "Archive validation FAILED:\n";
                foreach ($inspection['errors'] as $err) {
                    echo "  - {$err}\n";
                }
                return 1;
            }

            echo "Archive is VALID!\n";
            $manifest = $inspection['manifest'];
            echo "  Generator: " . ($manifest['generator'] ?? 'unknown') . "\n";
            echo "  Version: " . ($manifest['indieinabox_version'] ?? 'unknown') . "\n";
            echo "  Site: " . ($manifest['sitename'] ?? 'unknown') . " (" . ($manifest['fqdn'] ?? '') . ")\n";
            echo "  Created at: " . ($manifest['created_at'] ?? 'unknown') . "\n";
            $filesCount = isset($manifest['files']) && is_array($manifest['files']) ? count($manifest['files']) : 0;
            echo "  Verified files: {$filesCount}\n";
            return 0;
        }

        echo "Importing portable export bundle: {$archivePath}...\n";

        try {
            $result = $importService->import($archivePath, [
                'verify_checksums' => !$noVerify,
                'safety_backup' => !$noSafetyBackup,
                'restore_database' => true,
                'restore_content' => !$noContent,
                'restore_media' => !$noMedia,
                'rebuild_site' => !$noBuild,
            ]);

            echo "Import completed successfully!\n";
            if (!empty($result['safety_backup'])) {
                echo "  Safety backup created: {$result['safety_backup']}\n";
            }
            echo "  Files restored: {$result['files_restored']}\n";
            return 0;
        } catch (\Throwable $e) {
            echo "Import failed: " . $e->getMessage() . "\n";
            return 1;
        }
    }
}
