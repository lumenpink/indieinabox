<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Services\Contracts\InstallServiceInterface;
use Indieinabox\Services\InstallService;

/**
 * Command to initialize administrative credentials, domain FQDN, and initial blog identity.
 */
class SetupCommand extends AbstractCommand
{
    private InstallServiceInterface $installService;

    public function __construct(\Indieinabox\Site\Site $site, ?InstallServiceInterface $installService = null)
    {
        parent::__construct($site);
        $this->installService = $installService ?? new InstallService($site);
    }

    #[\Override]
    public function getName(): string
    {
        return 'setup';
    }

    #[\Override]
    public function getDescription(): string
    {
        return 'Runs setup to configure database, site identity, language, content directory, and admin credentials.';
    }

    #[\Override]
    public function getUsage(): string
    {
        return "php indieinabox.php setup [options]\n\n" .
               "Options:\n" .
               "  --name <sitename>        Site name (alias: --sitename, default: 'Indie In A Box')\n" .
               "  --fqdn <fqdn>            Site URL / FQDN (alias: --url, default: 'http://localhost:8080')\n" .
               "  --password <password>    Admin / IndieAuth password (auto-generated if omitted)\n" .
               "  --author <author>        Author display name\n" .
               "  --db <path>              SQLite database file path (alias: --database, default: 'data/indieinabox.sqlite')\n" .
               "  --data-dir <dir>         Data directory path for database and feeds\n" .
               "  --lang <lang>            Default language or comma-separated list (alias: --defaultlang, default: 'en')\n" .
               "  --content <dir>          Content directory path (alias: --contentdir, default: 'content')\n" .
               "  --no-build               Skip building static site after setup\n" .
               "  --non-interactive, -y    Run without prompting (flags or defaults used)";
    }

    #[\Override]
    public function execute(array $argv): int
    {
        if ($this->hasFlag($argv, '--help') || $this->hasFlag($argv, '-h') || in_array('help', $argv, true)) {
            echo $this->getUsage() . "\n";
            return 0;
        }

        $name = $this->getOption($argv, 'name') ?? $this->getOption($argv, 'sitename') ?? 'Indie In A Box';
        $fqdn = $this->getOption($argv, 'fqdn') ?? $this->getOption($argv, 'url') ?? 'http://localhost:8080';
        $password = $this->getOption($argv, 'password');
        $author = $this->getOption($argv, 'author') ?? $name;
        $dbPath = $this->getOption($argv, 'db') ?? $this->getOption($argv, 'database') ?? 'data/indieinabox.sqlite';
        $dataDir = $this->getOption($argv, 'data-dir');
        $lang = $this->getOption($argv, 'lang') ?? $this->getOption($argv, 'defaultlang') ?? 'en';
        $contentDir = $this->getOption($argv, 'content') ?? $this->getOption($argv, 'contentdir') ?? 'content';

        $shouldBuild = !$this->hasFlag($argv, '--no-build');

        $baseDir = !empty($this->site->paths->baseDir)
            ? $this->site->paths->baseDir
            : getcwd();
        if ($baseDir === false) {
            $baseDir = '.';
        }

        // Execute atomic 4-step installation workflow via shared InstallService
        $result = $this->installService->install([
            'base_dir' => $baseDir,
            'db_path' => $dbPath,
            'data_dir' => $dataDir,
            'sitename' => $name,
            'author' => $author,
            'fqdn' => $fqdn,
            'contentdir' => $contentDir,
            'lang' => $lang,
            'password' => $password,
            'build' => $shouldBuild,
        ]);

        // Output summary
        $sitename = (string) $result['sitename'];
        $fqdnClean = (string) $result['fqdn'];
        $contentDirClean = (string) $result['contentdir'];
        $fullDbPath = (string) $result['db_path'];
        $langs = (array) $result['lang'];
        $finalPassword = (string) $result['password'];
        $generatedPassword = (bool) $result['generated_password'];

        echo "\n=======================================================\n";
        echo "  Setup complete. Indieinabox setup completed successfully!\n";
        echo "=======================================================\n";
        echo "  Site Name:      {$sitename}\n";
        echo "  FQDN / URL:     {$fqdnClean}\n";
        echo "  Language:       " . implode(', ', $langs) . "\n";
        echo "  Content Dir:    {$contentDirClean}\n";
        echo "  Database:       {$fullDbPath}\n";
        if ($generatedPassword) {
            echo "  Admin Password: {$finalPassword} (Auto-generated)\n";
        } else {
            echo "  Admin Password: [Configured]\n";
        }
        echo "=======================================================\n\n";

        return 0;
    }
}
