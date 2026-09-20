<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\SiteBuilder\SiteBuilder;

/**
 * Command to orchestrate static site generation across HTML, Gemini, Gopher, and syndication feeds.
 */
class BuildCommand extends AbstractCommand
{
    #[\Override]
    public function getName(): string
    {
        return 'build';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Compiles static HTML, Gemini, Gopher, syndication feeds, and assets.';
    }

    #[\Override]
    public function getUsage(): string
    {
        return 'php indieinabox.php build [-s] [-f] [-a] [-M] [-m]';
    }

    #[\Override]
    public function execute(array $argv): int
    {
        $builder = new SiteBuilder($this->site);
        $builder->build();
        echo "Build complete\n";
        return 0;
    }
}
