<?php

declare(strict_types=1);

use Indieinabox\Site;

// Subclass the handler and router to mock external HTTP fetch requests
class MockWebmentionHandler extends \Indieinabox\WebmentionHandler
{
    public static array $mockResponses = [];

    protected function fetchUrl(string $url)
    {
        return self::$mockResponses[$url] ?? false;
    }
}

class TestWebRouter extends \Indieinabox\WebRouter
{
    protected function createWebmentionHandler(): \Indieinabox\WebmentionHandler
    {
        return new MockWebmentionHandler($this->site);
    }
}

$funcTempDir = __DIR__ . '/tmp_functional_webmention';

beforeEach(function () use ($funcTempDir) {
    if (!is_dir($funcTempDir)) {
        mkdir($funcTempDir, 0777, true);
    }
    // Clean mock responses before each test
    MockWebmentionHandler::$mockResponses = [];
    $_GET = [];
    $_POST = [];
    $_SERVER = [];
});

afterEach(function () use ($funcTempDir) {
    if (is_dir($funcTempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($funcTempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($funcTempDir);
    }
});

it('renders the help page for GET requests to beauty URLs and query params', function () use ($funcTempDir) {
    $site = new Site();
    $site->paths->baseDir = $funcTempDir;
    $site->metadata->fqdn = 'https://mysite.com';

    // 1. Test clean URL path '/webmention'
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/webmention';

    $router = new TestWebRouter($site);

    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toContain('Webmention Endpoint')
        ->and($output)->toContain('https://mysite.com');

    // 2. Test clean URL path '/webmentions'
    $_SERVER['REQUEST_URI'] = '/webmentions';
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toContain('Webmention Endpoint');

    // 3. Test query param '?webmention'
    $_SERVER['REQUEST_URI'] = '/some-page';
    $_GET['webmention'] = '';
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toContain('Webmention Endpoint');
});

it('rejects POST request with missing parameters', function () use ($funcTempDir) {
    $site = new Site();
    $site->paths->baseDir = $funcTempDir;
    $site->metadata->fqdn = 'https://mysite.com';

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/webmention';
    $_POST = []; // missing source and target

    $router = new TestWebRouter($site);
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect($json['status'])->toBe(400)
        ->and($json['message'])->toContain('Missing source or target parameters');
});

it('rejects POST request with invalid URLs', function () use ($funcTempDir) {
    $site = new Site();
    $site->paths->baseDir = $funcTempDir;
    $site->metadata->fqdn = 'https://mysite.com';

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/webmention';
    $_POST = [
        'source' => 'not-a-url',
        'target' => 'https://mysite.com/about'
    ];

    $router = new TestWebRouter($site);
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect($json['status'])->toBe(400)
        ->and($json['message'])->toContain('Invalid source or target URL');
});

it('rejects target URL mismatch with site FQDN', function () use ($funcTempDir) {
    $site = new Site();
    $site->paths->baseDir = $funcTempDir;
    $site->metadata->fqdn = 'https://mysite.com';

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/webmention';
    $_POST = [
        'source' => 'https://external.com/post',
        'target' => 'https://othersite.com/about' // FQDN mismatch
    ];

    $router = new TestWebRouter($site);
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect($json['status'])->toBe(400)
        ->and($json['message'])->toContain('Target URL does not belong to this site');
});

it('rejects target URL if target page does not exist on site', function () use ($funcTempDir) {
    $site = new Site();
    $site->paths->baseDir = $funcTempDir;
    $site->paths->outputDir = 'public';
    $site->metadata->fqdn = 'https://mysite.com';

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/webmention';
    $_POST = [
        'source' => 'https://external.com/post',
        'target' => 'https://mysite.com/about' // doesn't exist
    ];

    $router = new TestWebRouter($site);
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect($json['status'])->toBe(400)
        ->and($json['message'])->toContain('Target page not found on this site');
});

it('rejects target URL if source page does not link to target', function () use ($funcTempDir) {
    $site = new Site();
    $site->paths->baseDir = $funcTempDir;
    $site->paths->outputDir = 'public';
    $site->metadata->fqdn = 'https://mysite.com';

    // Create target file to exist
    $targetFileDir = $funcTempDir . '/public/about';
    mkdir($targetFileDir, 0777, true);
    file_put_contents($targetFileDir . '/index.html', '<h1>About Us</h1>');

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/webmention';
    $_POST = [
        'source' => 'https://external.com/post',
        'target' => 'https://mysite.com/about'
    ];

    // Source does not contain target link
    MockWebmentionHandler::$mockResponses['https://external.com/post'] = <<<HTML
<html>
<body>
    <p>Check this awesome blog!</p>
    <a href="https://different.com/about">Different Link</a>
</body>
</html>
HTML;

    $router = new TestWebRouter($site);
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect($json['status'])->toBe(400)
        ->and($json['message'])->toContain('Source page does not link to target page');
});

it('accepts and verifies valid webmention', function () use ($funcTempDir) {
    $site = new Site();
    $site->paths->baseDir = $funcTempDir;
    $site->paths->outputDir = 'public';
    $site->metadata->fqdn = 'https://mysite.com';

    // Create target file to exist
    $targetFileDir = $funcTempDir . '/public/about';
    mkdir($targetFileDir, 0777, true);
    file_put_contents($targetFileDir . '/index.html', '<h1>About Us</h1>');

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/webmention';
    $_POST = [
        'source' => 'https://external.com/post',
        'target' => 'https://mysite.com/about'
    ];

    // Source DOES contain target link
    MockWebmentionHandler::$mockResponses['https://external.com/post'] = <<<HTML
<html>
<head>
    <title>External Post Title</title>
</head>
<body>
    <div class="e-content">
        I read this great page: <a href="https://mysite.com/about">About Us Page</a>!
    </div>
</body>
</html>
HTML;

    $router = new TestWebRouter($site);
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect($json['status'])->toBe(202)
        ->and($json['message'])->toContain('Webmention accepted and processed');

    // Assert file was saved under data/webmentions/<md5_slug>.json
    $expectedFilename = md5('about') . '.json';
    $expectedFile = $funcTempDir . '/data/webmentions/' . $expectedFilename;
    expect(file_exists($expectedFile))->toBeTrue();

    $data = json_decode(file_get_contents($expectedFile), true);
    expect($data)->toHaveCount(1);
    expect($data[0]['source'])->toBe('https://external.com/post');
    expect($data[0]['title'])->toBe('External Post Title');
    expect($data[0]['text'])->toContain('I read this great page');
});
