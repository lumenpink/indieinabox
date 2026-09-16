<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Services\FetchFeedsService;

/**
 * Command to poll and ingest external subscriptions and twtxt feeds.
 */
class FetchCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'fetch';
    }

    /**
     * @return array<int, string>
     */
    public function getAliases(): array
    {
        return ['microsub:fetch'];
    }

    public function getDescription(): string
    {
        return 'Fetches remote RSS, Atom, and Twtxt subscription feeds into local storage.';
    }

    public function getUsage(): string
    {
        return 'php indieinabox.php fetch';
    }

    public function execute(array $argv): int
    {
        echo "Fetching feeds...\n";
        $fetcher = new FetchFeedsService();
        $fetcher->fetchAll();
        echo "Feeds fetched successfully.\n";
        return 0;
    }
}
