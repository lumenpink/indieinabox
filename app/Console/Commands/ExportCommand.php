<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Services\ExportService;

/**
 * Command to create a portable export bundle containing database, content, media, and manifest.
 */
class ExportCommand extends AbstractCommand
{
    #[\Override]
    public function getName(): string
    {
        return 'export';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Creates a portable export bundle (.tar.gz or .zip) with checksum manifest.';
    }

    #[\Override]
    public function getUsage(): string
    {
        return 'php indieinabox.php export [output-file] [--no-content] [--no-media]';
    }

    #[\Override]
    public function getAliases(): array
    {
        return ['backup:export'];
    }

    #[\Override]
    public function execute(array $argv): int
    {
        $skipContent = $this->hasFlag($argv, '--no-content');
        $skipMedia = $this->hasFlag($argv, '--no-media');

        // Look for positional output file or --output option
        $outputPath = $this->getOption($argv, 'output');
        if ($outputPath === null) {
            foreach ($argv as $arg) {
                if (!str_starts_with($arg, '-') && !in_array($arg, ['export', 'backup:export'], true) && !str_ends_with($arg, 'indieinabox.php')) {
                    $outputPath = $arg;
                    break;
                }
            }
        }

        echo "Generating portable export bundle...\n";

        $exportService = new ExportService($this->site);
        $result = $exportService->export($outputPath, [
            'skip_content' => $skipContent,
            'skip_media' => $skipMedia,
        ]);

        if (!$result['success']) {
            echo "Export failed: " . ($result['error'] ?? 'Unknown error') . "\n";
            return 1;
        }

        $fileCount = isset($result['manifest']['files']) ? count($result['manifest']['files']) : 0;
        echo "Export created successfully!\n";
        echo "  Path: {$result['path']}\n";
        echo "  Size: {$result['size_bytes']} bytes\n";
        echo "  Bundled files: {$fileCount}\n";

        return 0;
    }
}
