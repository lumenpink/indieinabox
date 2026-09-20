<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Core\Database;

/**
 * Command to initialize administrative credentials, domain FQDN, and initial blog identity.
 */
class SetupCommand extends AbstractCommand
{
    #[\Override]
    public function getName(): string
    {
        return 'setup';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Runs the initial setup wizard to configure admin credentials and domain FQDN.';
    }

    #[\Override]
    public function getUsage(): string
    {
        return 'php indieinabox.php setup [--password <pass>] [--name <sitename>] [--fqdn <fqdn>]';
    }

    #[\Override]
    public function execute(array $argv): int
    {
        $password = $this->getOption($argv, 'password');
        $name = $this->getOption($argv, 'name');
        $fqdn = $this->getOption($argv, 'fqdn');

        if (!$password) {
            echo "Enter admin password: ";
            $password = trim((string) fgets(STDIN));
        }
        if (!$name) {
            echo "Enter site name: ";
            $name = trim((string) fgets(STDIN));
        }
        if (!$fqdn) {
            echo "Enter FQDN (e.g. example.com): ";
            $fqdn = trim((string) fgets(STDIN));
        }

        if ($password && $name && $fqdn) {
            Database::saveSetting('admin_password', password_hash($password, PASSWORD_DEFAULT));
            Database::saveSetting('sitename', $name);
            Database::saveSetting('fqdn', $fqdn);
            Database::saveSetting('author', $name);

            echo "Setup complete.\n";
            return 0;
        }

        echo "Setup failed. All fields are required.\n";
        return 1;
    }
}
