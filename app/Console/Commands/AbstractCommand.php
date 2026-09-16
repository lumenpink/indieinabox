<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Console\Contracts\CommandInterface;
use Indieinabox\Site\Site;

/**
 * Base abstract command providing common CLI argument and option parsing helpers.
 */
abstract class AbstractCommand implements CommandInterface
{
    protected Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * @return array<int, string>
     */
    public function getAliases(): array
    {
        return [];
    }

    public function getUsage(): string
    {
        return 'php indieinabox.php ' . $this->getName();
    }

    /**
     * Extracts an option value following --<longOpt>.
     *
     * @param array<int, string> $argv
     */
    protected function getOption(array $argv, string $longOpt): ?string
    {
        foreach ($argv as $i => $arg) {
            if ($arg === "--{$longOpt}") {
                return $argv[$i + 1] ?? null;
            }
        }
        return null;
    }

    /**
     * Checks if a flag is present in argv.
     *
     * @param array<int, string> $argv
     */
    protected function hasFlag(array $argv, string $flag): bool
    {
        return in_array($flag, $argv, true);
    }
}
