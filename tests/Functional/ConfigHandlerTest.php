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
if (empty($intl)) {
    include __DIR__ . '/../../data/intl.php';
}
if (empty($kindspath)) {
    include __DIR__ . '/../../data/kindspath.php';
}

$configTestTempDir = __DIR__ . '/tmp_config_functional';

beforeEach(function () use ($configTestTempDir) {
    if (!is_dir($configTestTempDir)) {
        mkdir($configTestTempDir, 0777, true);
    }
    if (!is_dir($configTestTempDir . '/content')) {
        mkdir($configTestTempDir . '/content', 0777, true);
    }
    if (!is_dir($configTestTempDir . '/resources/views')) {
        mkdir($configTestTempDir . '/resources/views', 0777, true);
    }
    if (!is_dir($configTestTempDir . '/public')) {
        mkdir($configTestTempDir . '/public', 0777, true);
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

    $writtenFile = $configTestTempDir . '/.config.yml';
    expect(file_exists($writtenFile))->toBeTrue();

    $yaml = new Yaml();
    $data = $yaml->loadFile($writtenFile);

    expect($data['title'])->toBe('Bootstrap Title')
        ->and($data['sitename'])->toBe('Bootstrap Site Name')
        ->and($data['fqdn'])->toBe('https://bootstrapsite.com')
        ->and(password_verify('mysecurepwd123', $data['indieauth_password']))->toBeTrue();
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

    $codesDir = $configTestTempDir . '/data/indieauth/codes';
    if (!is_dir($codesDir)) {
        mkdir($codesDir, 0777, true);
    }
    file_put_contents($codesDir . '/' . md5($code) . '.json', json_encode($codeData));

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

    expect($_SESSION['admin_authenticated'])->toBeTrue()
        ->and(file_exists($codesDir . '/' . md5($code) . '.json'))->toBeFalse();
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
