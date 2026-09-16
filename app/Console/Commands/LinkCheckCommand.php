<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Services\LinkCheckerService;

/**
 * Command to scan built static site files and verify internal and external hyperlink validity.
 */
class LinkCheckCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'test-links';
    }

    public function getDescription(): string
    {
        return 'Crawls generated static HTML files to detect broken internal and external links.';
    }

    public function getUsage(): string
    {
        return 'php indieinabox.php test-links [--skip-external] [--internal-only] [--report <path>]';
    }

    public function execute(array $argv): int
    {
        $reportPath = null;
        $skipExternal = $this->hasFlag($argv, '--skip-external') || $this->hasFlag($argv, '--internal-only');

        foreach ($argv as $i => $arg) {
            if ($arg === '--report' && isset($argv[$i + 1])) {
                $reportPath = $argv[$i + 1];
                break;
            }
        }

        $checker = new LinkCheckerService($this->site);
        $checker->run($reportPath, $skipExternal);
        return 0;
    }
}
