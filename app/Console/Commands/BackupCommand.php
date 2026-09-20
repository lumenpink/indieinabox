<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Services\BackupService;

/**
 * Command to archive database, content, and media directories into a timestamped zip archive.
 */
class BackupCommand extends AbstractCommand
{
    #[\Override]
    public function getName(): string
    {
        return 'backup';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Creates a portable ZIP archive containing the database, markdown content, and media.';
    }

    #[\Override]
    public function getUsage(): string
    {
        return 'php indieinabox.php backup [--no-content] [--no-media]';
    }

    #[\Override]
    public function execute(array $argv): int
    {
        $skipContent = $this->hasFlag($argv, '--no-content');
        $skipMedia = $this->hasFlag($argv, '--no-media');

        $backupService = new BackupService($this->site);
        $backupService->run($skipContent, $skipMedia);
        return 0;
    }
}
