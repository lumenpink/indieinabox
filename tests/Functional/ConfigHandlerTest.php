<?php

declare(strict_types=1);

use Indieinabox\Site;
use Indieinabox\WebRouter;
use Indieinabox\Yaml;
use Indieinabox\Page;
use Indieinabox\MarkdownParser;
use Indieinabox\Markdown\FileProcessor;
use Indieinabox\Markdown\ContentProcessor;
use Indieinabox\Markdown\LanguageProcessor;

global $originaldaysofweek, $originalmonths, $intl, $kindspath;

$configTestTempDir = __DIR__ . '/tmp_config_functional';

beforeEach(function () use ($configTestTempDir) {
    if (!is_dir($configTestTempDir)) {
        mkdir($configTestTempDir, 0777, true);
    }
    if (!is_dir($configTestTempDir . '/content')) {
        mkdir($configTestTempDir . '/content', 0777, true);
    }
    
    $reflection = new \ReflectionClass(\Indieinabox\Database::class);
    $property = $reflection->getProperty('db');
    $property->setAccessible(true);
    $property->setValue(null, null);

    \Indieinabox\Database::connect(':memory:');
    $sql = file_get_contents(dirname(__DIR__, 2) . '/database.sql');
    \Indieinabox\Database::getDb()->exec($sql);
    if (!is_dir($configTestTempDir . '/resources/views')) {
        mkdir($configTestTempDir . '/resources/views', 0777, true);
    }
    if (!is_dir($configTestTempDir . '/public_html')) {
        mkdir($configTestTempDir . '/public_html', 0777, true);
    }
    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    $_SESSION = [];
});

afterEach(function () use ($configTestTempDir) {
    if (is_dir($configTestTempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($configTestTempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($configTestTempDir);
    }
});

it('renders setup form if config file is missing (Bootstrap Mode)', function () use ($configTestTempDir) {
    $site = new Site();
    $site->paths->baseDir = $configTestTempDir;
    $site->metadata->indieauthPassword = '';

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/config';

    $router = new WebRouter($site);
    
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toContain('Setup Setup Setup!')
        ->and($output)->toContain('Indieinabox is not configured yet');
});

it('processes bootstrap password configuration and writes .config.yml', function () use ($configTestTempDir) {
    $site = new Site();
    $site->paths->baseDir = $configTestTempDir;
    $site->metadata->indieauthPassword = '';

    // Create a mock content directory (already created in beforeEach)

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/config';
    $_POST = [
        'indieauth_password' => 'mysecurepwd123',
        'title' => 'Bootstrap Title',
        'sitename' => 'Bootstrap Site Name',
        'fqdn' => 'https://bootstrapsite.com'
    ];

    $router = new WebRouter($site);
    
    ob_start();
    try {
        $router->handleRequest();
    } catch (\Exception $e) {
        // SiteBuilder might fail to compile pages since content directory is empty, but config should be written
    }
    ob_get_clean();

    $db = \Indieinabox\Database::getDb();
    $stmt = $db->query("SELECT value FROM settings WHERE key = 'sitename'");
    $sitename = trim($stmt->fetchColumn(), '"');
    
    expect($sitename)->toBe('Bootstrap Site Name');
});

it('redirects to auth if password is set but user is unauthenticated', function () use ($configTestTempDir) {
    $site = new Site();
    $site->paths->baseDir = $configTestTempDir;
    $site->metadata->indieauthPassword = 'configured_password';
    $site->metadata->fqdn = 'https://mysite.com';

    // Mock config file exists
    $yaml = new Yaml();
    file_put_contents($configTestTempDir . '/.config.yml', $yaml->dump(['indieauth_password' => 'configured_password']));

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/config';

    $router = new WebRouter($site);
    
    // Simulate redirection headers
    $redirectHeader = null;
    if (!function_exists('header')) {
        // in PHPUnit environment, headers might output directly unless intercepted
    }
    
    ob_start();
    try {
        $router->handleRequest();
    } catch (\Exception $e) {
        // Catch redirect exit
    }
    ob_get_clean();

    // Check if session contains auth_state
    expect($_SESSION['auth_state'])->not->toBeEmpty();
});

it('authenticates user and sets session on valid authorization callback', function () use ($configTestTempDir) {
    $site = new Site();
    $site->paths->baseDir = $configTestTempDir;
    $site->metadata->indieauthPassword = 'configured_password';
    $site->metadata->fqdn = 'https://mysite.com';

    // Mock config file exists
    $yaml = new Yaml();
    file_put_contents($configTestTempDir . '/.config.yml', $yaml->dump(['indieauth_password' => 'configured_password']));

    // Store state in session and write a mock code file
    $_SESSION['auth_state'] = 'state_xyz';

    $code = 'auth_code_111';
    $codeData = [
        'code' => $code,
        'client_id' => 'https://mysite.com/config',
        'redirect_uri' => 'https://mysite.com/config',
        'state' => 'state_xyz',
        'expires_at' => time() + 600,
        'me' => 'https://mysite.com/'
    ];

    $db = \Indieinabox\Database::getDb();
    $stmt = $db->prepare('INSERT INTO indieauth_codes (code_hash, client_id, redirect_uri, state, scope, code_challenge, code_challenge_method, expires_at, me) VALUES (:hash, :client_id, :redirect_uri, :state, :scope, :challenge, :method, :expires, :me)');
    $stmt->bindValue(':hash', hash('sha256', $code));
    $stmt->bindValue(':client_id', 'https://mysite.com/config');
    $stmt->bindValue(':redirect_uri', 'https://mysite.com/config');
    $stmt->bindValue(':state', 'state_xyz');
    $stmt->bindValue(':scope', '');
    $stmt->bindValue(':challenge', '');
    $stmt->bindValue(':method', '');
    $stmt->bindValue(':expires', time() + 600);
    $stmt->bindValue(':me', 'https://mysite.com/');
    $stmt->execute();

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/config';
    $_GET = [
        'code' => $code,
        'state' => 'state_xyz'
    ];

    $router = new WebRouter($site);
    
    ob_start();
    try {
        $router->handleRequest();
    } catch (\Exception $e) {
        // Catch redirect exit
    }
    ob_get_clean();

    $db = \Indieinabox\Database::getDb();
    $stmt = $db->query("SELECT COUNT(*) FROM indieauth_codes WHERE code_hash = '" . hash('sha256', $code) . "'");
    $count = (int)$stmt->fetchColumn();

    expect($_SESSION['admin_authenticated'])->toBeTrue()
        ->and($count)->toBe(0);
});

it('formats slugs correctly for pretty links and ugly links', function () use ($configTestTempDir) {
    global $site;
    $site = new Site();
    $site->paths->baseDir = $configTestTempDir;
    $site->paths->contentDir = 'content';

    // Write a mock page file
    $contentFile = $configTestTempDir . '/content/about.md';
    if (!is_dir($configTestTempDir . '/content')) {
        mkdir($configTestTempDir . '/content', 0777, true);
    }
    file_put_contents($contentFile, "---\ntitle: About Us\nlayout: page\n---\nHello World.");

    $fileProcessor = new FileProcessor($site, $configTestTempDir);
    $contentProcessor = new ContentProcessor();
    $languageProcessor = new LanguageProcessor($site, new \Indieinabox\Translations\UrlTranslations([]));

    // Case 1: Pretty Links = true (default)
    $site->options->prettylinks = true;
    $parser1 = new MarkdownParser($fileProcessor, $contentProcessor, $languageProcessor, $site);
    $page1 = $parser1->parse($contentFile);
    expect($page1->slug)->toBe('about/');

    // Case 2: Pretty Links = false (ugly links)
    $site->options->prettylinks = false;
    $parser2 = new MarkdownParser($fileProcessor, $contentProcessor, $languageProcessor, $site);
    $page2 = $parser2->parse($contentFile);
    expect($page2->slug)->toBe('about.html');
});

it('saves config and processes lang/kind removals and fallbacks', function () use ($configTestTempDir) {
    $site = new Site();
    $site->paths->baseDir = $configTestTempDir;
    $site->metadata->indieauthPassword = 'configured_password';
    $site->metadata->fqdn = 'https://mysite.com';

    // Mock initial config
    $yaml = new Yaml();
    $initialConfig = [
        'indieauth_password' => 'configured_password',
        'fqdn' => 'https://mysite.com',
        'lang' => ['pt', 'en'],
        'kinds' => [
            'photo' => [
                'content_dir' => 'fotos',
                'display_mode' => 'thumbnail_snippet'
            ],
            'note' => [
                'content_dir' => 'notas',
                'display_mode' => 'full_content'
            ]
        ]
    ];
    file_put_contents($configTestTempDir . '/.config.yml', $yaml->dump($initialConfig));

    // Authenticated session
    $_SESSION['admin_authenticated'] = true;

    // Simulate POST request to save settings, also requesting to remove language 'pt'
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/config';
    $_POST = [
        'title' => 'My Site',
        'sitename' => 'My Site Name',
        'fqdn' => 'https://mysite.com',
        'lang' => ['pt', 'en'],
        'remove_lang' => 'pt',
        'kinds' => [
            'photo' => [
                'content_dir' => 'fotos',
                'display_mode' => 'thumbnail_snippet'
            ],
            'note' => [
                'content_dir' => 'notas',
                'display_mode' => 'full_content'
            ]
        ]
    ];

    $router = new WebRouter($site);
    ob_start();
    try {
        $router->handleRequest();
    } catch (\Exception $e) {
        // Redirect or build pipeline might exit/throw
    }
    ob_get_clean();

    $db = \Indieinabox\Database::getDb();
    $stmt = $db->query("SELECT value FROM settings WHERE key = 'lang'");
    $data = json_decode($stmt->fetchColumn(), true);
    expect($data)->toBe(['en']);

    // Now let's remove the remaining language 'en' (which will result in zero languages, defaulting to ['en'])
    $_POST = [
        'title' => 'My Site',
        'sitename' => 'My Site Name',
        'fqdn' => 'https://mysite.com',
        'lang' => ['en'],
        'remove_lang' => 'en',
        'kinds' => [
            'photo' => [
                'content_dir' => 'fotos',
                'display_mode' => 'thumbnail_snippet'
            ]
        ]
    ];

    ob_start();
    try {
        $router->handleRequest();
    } catch (\Exception $e) {}
    ob_get_clean();

    $db = \Indieinabox\Database::getDb();
    $stmt = $db->query("SELECT value FROM settings WHERE key = 'lang'");
    $langData = json_decode($stmt->fetchColumn(), true);
    expect($langData)->toBe(['en']); // Default fallback to ['en']

    // Now let's remove the remaining kind 'photo' (which will result in zero kinds, defaulting to 'article')
    $_POST = [
        'title' => 'My Site',
        'sitename' => 'My Site Name',
        'fqdn' => 'https://mysite.com',
        'lang' => ['en'],
        'remove_kind' => 'photo',
        'kinds' => [
            'photo' => [
                'content_dir' => 'fotos',
                'display_mode' => 'thumbnail_snippet'
            ]
        ]
    ];

    ob_start();
    try {
        $router->handleRequest();
    } catch (\Exception $e) {}
    ob_get_clean();

    $stmt = $db->query("SELECT config_json FROM kinds");
    $kindsData = [];
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $kind = json_decode($row['config_json'], true);
        if ($kind) {
            $kindsData[] = $kind;
        }
    }
    
    // There should only be 'article' left in the DB, though they are stored with their keys.
    // Wait, the test checks array_keys. We should SELECT kind_key, config_json
    $stmt = $db->query("SELECT kind_key, config_json FROM kinds");
    $kinds = [];
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $kinds[$row['kind_key']] = json_decode($row['config_json'], true);
    }
    
    expect(array_keys($kinds))->toBe(['article']);
    expect($kinds['article']['content_dir'])->toBe('articles');
});
