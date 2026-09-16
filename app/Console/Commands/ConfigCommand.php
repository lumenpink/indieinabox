<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Core\Database;

/**
 * Command to inspect and manipulate runtime site configuration settings via CLI.
 */
class ConfigCommand extends AbstractCommand
{
    public function getName(): string
    {
        return 'config';
    }

    public function getDescription(): string
    {
        return 'Gets or sets application configuration values stored in the database.';
    }

    public function getUsage(): string
    {
        return "Usage:\n" .
               "  php indieinabox.php config set --key <key> --value <value>\n" .
               "  php indieinabox.php config get --key <key>";
    }

    public function execute(array $argv): int
    {
        $subcommand = $argv[2] ?? '';
        if ($subcommand === 'set') {
            $key = $this->getOption($argv, 'key');
            $value = $this->getOption($argv, 'value');

            if (!$key || $value === null) {
                echo "Usage: config set --key <key> --value <value>\n";
                return 1;
            }

            Database::saveSetting($key, $value);
            echo "Config '$key' updated.\n";
            return 0;
        }

        if ($subcommand === 'get') {
            $key = $this->getOption($argv, 'key');

            if (!$key) {
                echo "Usage: config get --key <key>\n";
                return 1;
            }

            $val = Database::getSetting($key);
            echo "Config '$key': " . ($val ?? 'null') . "\n";
            return 0;
        }

        echo $this->getUsage() . "\n";
        return 1;
    }
}
