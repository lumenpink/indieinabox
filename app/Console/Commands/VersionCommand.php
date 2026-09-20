<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Core\Version;

/**
 * Command to display current application version, runtime mode, and build timestamp.
 */
class VersionCommand extends AbstractCommand
{
    #[\Override]
    public function getName(): string
    {
        return 'version';
    }

    /**
     * @return array<int, string>
     */
    #[\Override]
    public function getAliases(): array
    {
        return ['-v', '--version'];
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Displays the installed Indieinabox version, runtime mode, and build date.';
    }

    #[\Override]
    public function getUsage(): string
    {
        return 'php indieinabox.php version';
    }

    #[\Override]
    public function execute(array $argv): int
    {
        $version = Version::get();
        $isCompiled = Version::isCompiled();
        $buildDate = Version::getBuildDate();

        echo "Indieinabox version: {$version}\n";
        echo "Runtime Mode: " . ($isCompiled ? "Single-file compiled binary" : "Development repository") . "\n";
        if ($buildDate !== null) {
            echo "Build Date: {$buildDate}\n";
        }
        echo "PHP Version: " . PHP_VERSION . "\n";
        return 0;
    }
}
