<?php

declare(strict_types=1);

namespace Indieinabox\Console\Commands;

use Indieinabox\Core\Database;
use Indieinabox\SiteBuilder\SiteBuilder;

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
        return 'Runs setup to configure database, site identity, language, content directory, and admin credentials.';
    }

    #[\Override]
    public function getUsage(): string
    {
        return "php indieinabox.php setup [options]\n\n" .
               "Options:\n" .
               "  --name <sitename>        Site name (alias: --sitename)\n" .
               "  --fqdn <fqdn>            Site URL / FQDN (alias: --url)\n" .
               "  --password <password>    Admin / IndieAuth password\n" .
               "  --author <author>        Author display name\n" .
               "  --db <path>              SQLite database file path (alias: --database)\n" .
               "  --data-dir <dir>         Data directory path for database and feeds\n" .
               "  --lang <lang>            Default language or comma-separated list (alias: --defaultlang)\n" .
               "  --content <dir>          Content directory path (alias: --contentdir)\n" .
               "  --build                  Automatically rebuild static site after setup\n" .
               "  --non-interactive, -y    Run without prompting (use flags or sensible defaults)";
    }

    #[\Override]
    public function execute(array $argv): int
    {
        if ($this->hasFlag($argv, '--help') || $this->hasFlag($argv, '-h') || in_array('help', $argv, true)) {
            echo $this->getUsage() . "\n";
            return 0;
        }

        $name = $this->getOption($argv, 'name') ?? $this->getOption($argv, 'sitename');
        $fqdn = $this->getOption($argv, 'fqdn') ?? $this->getOption($argv, 'url');
        $password = $this->getOption($argv, 'password');
        $author = $this->getOption($argv, 'author');
        $dbPath = $this->getOption($argv, 'db') ?? $this->getOption($argv, 'database');
        $dataDir = $this->getOption($argv, 'data-dir');
        $lang = $this->getOption($argv, 'lang') ?? $this->getOption($argv, 'defaultlang');
        $contentDir = $this->getOption($argv, 'content') ?? $this->getOption($argv, 'contentdir');

        $nonInteractive = $this->hasFlag($argv, '--non-interactive')
            || $this->hasFlag($argv, '-y')
            || $this->hasFlag($argv, '--yes');
        $shouldBuild = $this->hasFlag($argv, '--build');

        $hasAnyOption = ($name !== null)
            || ($fqdn !== null)
            || ($password !== null)
            || ($dbPath !== null)
            || ($lang !== null)
            || ($contentDir !== null);

        $isTty = function_exists('stream_isatty') && defined('STDIN') && @stream_isatty(STDIN);
        $isInteractive = !$nonInteractive && !$hasAnyOption && $isTty;

        if ($isInteractive) {
            echo "Site Name [My Site Name]: ";
            $input = trim((string) fgets(STDIN));
            $name = $input !== '' ? $input : 'My Site Name';

            echo "Site URL / FQDN [http://localhost:8080]: ";
            $input = trim((string) fgets(STDIN));
            $fqdn = $input !== '' ? $input : 'http://localhost:8080';

            echo "Default language [en]: ";
            $input = trim((string) fgets(STDIN));
            $lang = $input !== '' ? $input : 'en';

            echo "Content directory [content]: ";
            $input = trim((string) fgets(STDIN));
            $contentDir = $input !== '' ? $input : 'content';

            echo "Database path [data/indieinabox.sqlite]: ";
            $input = trim((string) fgets(STDIN));
            $dbPath = $input !== '' ? $input : 'data/indieinabox.sqlite';

            echo "Admin / IndieAuth Password: ";
            $input = trim((string) fgets(STDIN));
            $password = $input !== '' ? $input : null;
        }

        $name = $name ?? 'My Site Name';
        $fqdn = $fqdn ?? 'http://localhost:8080';
        $lang = $lang ?? 'en';
        $contentDir = $contentDir ?? 'content';
        $dbPath = $dbPath ?? 'data/indieinabox.sqlite';
        $author = $author ?? $name;

        $generatedPassword = false;
        if (empty($password)) {
            $password = bin2hex(random_bytes(8));
            $generatedPassword = true;
        }

        $baseDir = !empty($this->site->paths->baseDir)
            ? $this->site->paths->baseDir
            : (!empty(Database::$dataDir) ? Database::$dataDir : getcwd());
        if ($baseDir === false) {
            $baseDir = '.';
        }

        // 1. Resolve DB & Data Directory paths
        $isAbsoluteDb = str_starts_with($dbPath, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $dbPath);
        $fullDbPath = $isAbsoluteDb ? $dbPath : $baseDir . DIRECTORY_SEPARATOR . $dbPath;

        if ($dataDir === null || $dataDir === '') {
            $fullDataDir = dirname($fullDbPath);
        } else {
            $isAbsoluteData = str_starts_with($dataDir, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $dataDir);
            $fullDataDir = $isAbsoluteData ? $dataDir : $baseDir . DIRECTORY_SEPARATOR . $dataDir;
        }

        // 2. Ensure directories exist
        if (!is_dir($fullDataDir)) {
            @mkdir($fullDataDir, 0755, true);
        }
        $microsubDir = $fullDataDir . DIRECTORY_SEPARATOR . 'microsub';
        if (!is_dir($microsubDir)) {
            @mkdir($microsubDir, 0755, true);
        }
        $apDir = $fullDataDir . DIRECTORY_SEPARATOR . 'activitypub';
        if (!is_dir($apDir)) {
            @mkdir($apDir, 0755, true);
        }

        // 3. Write .config.php
        $configFile = $baseDir . DIRECTORY_SEPARATOR . '.config.php';
        $configContent = "<?php\n\nreturn [\n    'data_dir' => '" . addcslashes($fullDataDir, "'\\") . "',\n    'db_path' => '" . addcslashes($fullDbPath, "'\\") . "'\n];\n";
        @file_put_contents($configFile, $configContent);

        // 4. Connect database and initialize schema
        Database::$dataDir = $fullDataDir;
        Database::connect($fullDbPath);
        $pdo = Database::getDb();
        Database::initializeSchema($pdo);

        // 5. Ensure content directory and seed welcome files if empty
        $isAbsoluteContent = str_starts_with($contentDir, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $contentDir);
        $fullContentDir = $isAbsoluteContent ? $contentDir : $baseDir . DIRECTORY_SEPARATOR . $contentDir;

        $articlesDir = $fullContentDir . DIRECTORY_SEPARATOR . 'articles';
        $notesDir = $fullContentDir . DIRECTORY_SEPARATOR . 'notes';
        if (!is_dir($articlesDir)) {
            @mkdir($articlesDir, 0755, true);
        }
        if (!is_dir($notesDir)) {
            @mkdir($notesDir, 0755, true);
        }

        $existingArticles = glob($articlesDir . '/*.md');
        if (empty($existingArticles)) {
            $welcomeArticle = "---\ntitle: Welcome to Indieinabox\ndate: " . date('Y-m-d H:i:s') . "\n---\n\nWelcome to your new Indieinabox site!\n\nIndieinabox is a lightweight, static-site generator and IndieWeb-compatible server built for individuals who want to own their content.\n";
            file_put_contents($articlesDir . DIRECTORY_SEPARATOR . 'welcome-to-indieinabox.md', $welcomeArticle);
        }

        $existingNotes = glob($notesDir . '/*.md');
        if (empty($existingNotes)) {
            $welcomeNote = "---\ndate: " . date('Y-m-d H:i:s') . "\n---\n\nThere is immense power in having total control over your own data. Welcome to the IndieWeb.\n";
            file_put_contents($notesDir . DIRECTORY_SEPARATOR . 'first-note.md', $welcomeNote);
        }

        // 6. Save site settings
        $langs = array_values(array_filter(array_map('trim', explode(',', $lang))));
        if (empty($langs)) {
            $langs = ['en'];
        }
        $defaultLang = $langs[0];
        $fqdnClean = rtrim($fqdn, '/');

        Database::saveSetting('sitename', $name);
        Database::saveSetting('fqdn', $fqdnClean);
        Database::saveSetting('author', $author);
        Database::saveSetting('contentdir', $contentDir);
        Database::saveSetting('outputdir', 'public');
        Database::saveSetting('base', '/');
        Database::saveSetting('lang', $langs);
        Database::saveSetting('defaultlang', $defaultLang);
        Database::saveSetting('support', ['md', 'txt', 'html', 'htm']);
        Database::saveSetting('buildall', true);
        Database::saveSetting('htmlpostprocessing', 'minify');
        Database::saveSetting('prettylinks', true);
        Database::saveSetting('indieauth_password', password_hash($password, PASSWORD_BCRYPT));
        Database::saveSetting('admin_password', password_hash($password, PASSWORD_DEFAULT));

        // 7. Optional initial build
        if ($shouldBuild && class_exists(SiteBuilder::class)) {
            echo "Running initial site build...\n";
            $this->site->config = Database::getAllSettings();
            $this->site->config['kinds'] = Database::getKinds();
            $this->site->config['translations'] = Database::getTranslations();
            $this->site->config['urltranslations'] = Database::getUrlTranslations();
            $this->site->paths->contentDir = $contentDir;
            $builder = new SiteBuilder($this->site);
            $builder->build();
        }

        // 8. Output summary
        echo "\n=======================================================\n";
        echo "  Setup complete. Indieinabox setup completed successfully!\n";
        echo "=======================================================\n";
        echo "  Site Name:      {$name}\n";
        echo "  FQDN / URL:     {$fqdnClean}\n";
        echo "  Language:       " . implode(', ', $langs) . "\n";
        echo "  Content Dir:    {$contentDir}\n";
        echo "  Database:       {$fullDbPath}\n";
        if ($generatedPassword) {
            echo "  Admin Password: {$password} (Auto-generated)\n";
        } else {
            echo "  Admin Password: [Configured]\n";
        }
        echo "=======================================================\n\n";

        return 0;
    }
}
