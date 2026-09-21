<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Core\Database;
use Indieinabox\Services\Contracts\InstallServiceInterface;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\SiteBuilder;

/**
 * Service responsible for the atomic 4-step installation workflow:
 * 1. Configure DB location (and data directory, writing .config.php)
 * 2. Execute DB install / migration
 * 3. Configure site settings & seed default welcome content
 * 4. Build the initial static site
 */
class InstallService implements InstallServiceInterface
{
    private ?Site $site;

    public function __construct(?Site $site = null)
    {
        $this->site = $site;
    }

    /**
     * Executes the installation workflow atomically in sequence.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    #[\Override]
    public function install(array $params = []): array
    {
        $baseDir = (string) ($params['base_dir'] ?? ($this->site?->paths->baseDir ?? (!empty(Database::$dataDir) ? Database::$dataDir : getcwd())));
        if ($baseDir === '' || $baseDir === false) {
            $baseDir = '.';
        }

        // 1. Resolve and configure DB location
        $dbPath = (string) ($params['db_path'] ?? ($params['db'] ?? ($params['database'] ?? 'data/indieinabox.sqlite')));
        $dataDir = isset($params['data_dir']) ? (string) $params['data_dir'] : null;

        [$fullDbPath, $fullDataDir] = $this->configureDbLocation($baseDir, $dbPath, $dataDir);

        // 2. Execute DB install / migration
        $this->migrateDatabase($fullDbPath, $fullDataDir);

        // 3. Configure site settings and seed initial content
        $name = (string) ($params['sitename'] ?? ($params['name'] ?? 'Indie In A Box'));
        $author = (string) ($params['author'] ?? $name);
        $fqdn = rtrim((string) ($params['fqdn'] ?? ($params['url'] ?? 'http://localhost:8080')), '/');
        $contentDir = (string) ($params['contentdir'] ?? ($params['content'] ?? 'content'));

        $rawLang = $params['lang'] ?? ($params['defaultlang'] ?? 'en');
        if (is_array($rawLang)) {
            $langs = array_values(array_filter(array_map('trim', $rawLang)));
        } else {
            $langs = array_values(array_filter(array_map('trim', explode(',', (string) $rawLang))));
        }
        if (empty($langs)) {
            $langs = ['en'];
        }
        $defaultLang = $langs[0];

        $password = (string) ($params['password'] ?? '');
        $generatedPassword = false;
        if ($password === '') {
            $password = bin2hex(random_bytes(8));
            $generatedPassword = true;
        }

        $this->configureSiteSettings($baseDir, [
            'sitename' => $name,
            'author' => $author,
            'fqdn' => $fqdn,
            'contentdir' => $contentDir,
            'langs' => $langs,
            'defaultlang' => $defaultLang,
            'password' => $password,
        ]);

        // 4. Build the site
        $shouldBuild = (bool) ($params['build'] ?? true);
        if ($shouldBuild && class_exists(SiteBuilder::class)) {
            $this->buildSite($baseDir, $fullDataDir, $contentDir);
        }

        return [
            'success' => true,
            'sitename' => $name,
            'author' => $author,
            'fqdn' => $fqdn,
            'contentdir' => $contentDir,
            'db_path' => $fullDbPath,
            'data_dir' => $fullDataDir,
            'lang' => $langs,
            'password' => $password,
            'generated_password' => $generatedPassword,
            'built' => $shouldBuild,
        ];
    }

    /**
     * Step 1: Configures database and data directory paths and writes .config.php.
     *
     * @return array{0: string, 1: string} [fullDbPath, fullDataDir]
     */
    public function configureDbLocation(string $baseDir, string $dbPath, ?string $dataDir = null): array
    {
        $isAbsoluteDb = str_starts_with($dbPath, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $dbPath);
        $fullDbPath = $isAbsoluteDb ? $dbPath : $baseDir . DIRECTORY_SEPARATOR . $dbPath;

        if ($dataDir === null || $dataDir === '') {
            $fullDataDir = dirname($fullDbPath);
        } else {
            $isAbsoluteData = str_starts_with($dataDir, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $dataDir);
            $fullDataDir = $isAbsoluteData ? $dataDir : $baseDir . DIRECTORY_SEPARATOR . $dataDir;
        }

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

        $configFile = $baseDir . DIRECTORY_SEPARATOR . '.config.php';
        $configContent = "<?php\n\nreturn [\n    'data_dir' => '" . addcslashes($fullDataDir, "'\\") . "',\n    'db_path' => '" . addcslashes($fullDbPath, "'\\") . "'\n];\n";
        @file_put_contents($configFile, $configContent);

        return [$fullDbPath, $fullDataDir];
    }

    /**
     * Step 2: Connects to SQLite and applies database schema migrations.
     */
    public function migrateDatabase(string $fullDbPath, string $fullDataDir): void
    {
        Database::$dataDir = $fullDataDir;
        Database::connect($fullDbPath);
        Database::initializeSchema(Database::getDb());
    }

    /**
     * Step 3: Persists site parameters into the database and seeds default welcome content.
     *
     * @param array<string, mixed> $settings
     */
    public function configureSiteSettings(string $baseDir, array $settings): void
    {
        $contentDir = (string) ($settings['contentdir'] ?? 'content');
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

        $password = (string) ($settings['password'] ?? '');

        Database::saveSetting('sitename', $settings['sitename'] ?? 'Indie In A Box');
        Database::saveSetting('fqdn', $settings['fqdn'] ?? 'http://localhost:8080');
        Database::saveSetting('author', $settings['author'] ?? ($settings['sitename'] ?? 'Indie In A Box'));
        Database::saveSetting('contentdir', $contentDir);
        Database::saveSetting('outputdir', 'public');
        Database::saveSetting('base', '/');
        Database::saveSetting('lang', $settings['langs'] ?? ['en']);
        Database::saveSetting('defaultlang', $settings['defaultlang'] ?? 'en');
        Database::saveSetting('support', ['md', 'txt', 'html', 'htm']);
        Database::saveSetting('buildall', true);
        Database::saveSetting('htmlpostprocessing', 'minify');
        Database::saveSetting('prettylinks', true);

        if ($password !== '') {
            Database::saveSetting('indieauth_password', password_hash($password, PASSWORD_BCRYPT));
            Database::saveSetting('admin_password', password_hash($password, PASSWORD_DEFAULT));
        }
    }

    /**
     * Step 4: Executes initial static site build.
     */
    public function buildSite(string $baseDir, string $fullDataDir, string $contentDir): void
    {
        $site = $this->site ?? new Site();
        $site->paths->baseDir = $baseDir;
        $site->paths->contentDir = $contentDir;

        $settings = Database::getAllSettings();
        $site->config = $settings;
        $site->config['kinds'] = Database::getKinds();
        $site->config['translations'] = Database::getTranslations();
        $site->config['urltranslations'] = Database::getUrlTranslations();

        $baseOut = (string) ($settings['outputdir'] ?? 'public');
        $site->paths->outputDirHtml = $baseOut . '_html';
        $site->paths->outputDirGemini = $baseOut . '_gemini';
        $site->paths->outputDirGopher = $baseOut . '_gopher';
        $site->paths->outputDirMedia = $baseOut . '_media';

        if (isset($settings['active_theme']) && $settings['active_theme'] !== 'default') {
            $site->paths->themeDir = $fullDataDir . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . (string) $settings['active_theme'];
        } else {
            $site->paths->themeDir = $baseDir . DIRECTORY_SEPARATOR . 'resources';
        }

        $builder = new SiteBuilder($site);
        $builder->build();
    }
}
