<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\BackgroundWorker\BackgroundWorker;

/**
 * Command to execute periodic background worker pipelines.
 */
class CronCommand extends AbstractCommand
{
    #[\Override]
    public function getName(): string
    {
        return 'cron';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Executes scheduled background tasks (ActivityPub inbox/outbox, outgoing webmentions, archiving).';
    }

    #[\Override]
    public function getUsage(): string
    {
        return 'php indieinabox.php cron';
    }

    #[\Override]
    public function execute(array $argv): int
    {
        $worker = new BackgroundWorker($this->site);
        $worker->runAll();
        return 0;
    }
}
