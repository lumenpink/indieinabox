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

class MockBackgroundWorker extends \Indieinabox\BackgroundWorker
{
    protected function fetchUrl(string $url)
    {
        return MockWebmentionHandler::$mockResponses[$url] ?? parent::fetchUrl($url);
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
    
    // Set up test database
    $ref = new \ReflectionClass(\Indieinabox\Database::class);
    $prop = $ref->getProperty('db');
    $prop->setAccessible(true);
    $prop->setValue(null, null);
    
    $testDbPath = $funcTempDir . '/test.sqlite';
    \Indieinabox\Database::$dataDir = $funcTempDir . '/data';
    \Indieinabox\Database::connect($testDbPath);
    $db = \Indieinabox\Database::getDb();
    $db->exec(file_get_contents(dirname(__DIR__, 2) . '/database.sql'));
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
    $targetFileDir = $funcTempDir . '/public_html/about';
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
    expect($json['status'])->toBe(202)
        ->and($json['message'])->toContain('Webmention accepted and queued for processing');
});

it('accepts and verifies valid webmention', function () use ($funcTempDir) {
    $site = new Site();
    $site->paths->baseDir = $funcTempDir;
    $site->paths->outputDir = 'public';
    $site->metadata->fqdn = 'https://mysite.com';

    // Create target file to exist
    $targetFileDir = $funcTempDir . '/public_html/about';
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
        ->and($json['message'])->toContain('Webmention accepted and queued for processing');

    // Run BackgroundWorker to process the queued payload
    $worker = new MockBackgroundWorker($site);
    $worker->processInboxQueue();

    // Assert webmention was saved to markdown file
    $expectedHash = md5('about');
    $source = 'https://external.com/post';
    $mdFile = $funcTempDir . '/data/microsub/inbox/notifications/' . $expectedHash . '_' . md5($source) . '.md';
    
    expect(file_exists($mdFile))->toBeTrue();
    
    $content = file_get_contents($mdFile);
    preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches);
    $yamlParser = new \Indieinabox\Yaml();
    $data = $yamlParser->loadString($matches[1]);
    
    expect($data['source'])->toBe($source);
    expect($data['author_name'])->toBe('External Post Title');
    expect(trim($matches[2]))->toContain('I read this great page');
});




function setupWebmentionTest(string $funcTempDir, string $sourceHtml): array
{
    $site = new Site();
    $site->paths->baseDir = $funcTempDir;
    $site->paths->outputDir = 'public';
    $site->metadata->fqdn = 'https://mysite.com';

    $targetFileDir = $funcTempDir . '/public_html/about';
    @mkdir($targetFileDir, 0777, true);
    file_put_contents($targetFileDir . '/index.html', '<h1>About Us</h1>');

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/webmention';
    $_POST = [
        'source' => 'https://external.com/styled-post',
        'target' => 'https://mysite.com/about'
    ];

    MockWebmentionHandler::$mockResponses['https://external.com/styled-post'] = $sourceHtml;

    $router = new TestWebRouter($site);
    ob_start();
    $router->handleRequest();
    ob_get_clean();

    $worker = new MockBackgroundWorker($site);
    $worker->processInboxQueue();

    $expectedHash = md5('about');
    $mdFile = $funcTempDir . '/data/microsub/inbox/notifications/' . $expectedHash . '_' . md5($_POST['source']) . '.md';
    if (file_exists($mdFile)) {
        $content = file_get_contents($mdFile);
        preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches);
        $yamlParser = new \Indieinabox\Yaml();
        $data = $yamlParser->loadString($matches[1]);
        $data['text'] = trim($matches[2]);
        return [$data];
    }
    return [];
}

// 1. no whostyle
it('accepts webmention with no whostyle', function () use ($funcTempDir) {
    $html = '<html><body><a href="https://mysite.com/about">Link</a></body></html>';
    $data = setupWebmentionTest($funcTempDir, $html);
    expect(empty($data[0]['whostyle']))->toBeTrue();
});

// 2. valid whostyle hash in meta tag
it('accepts valid whostyle in meta', function () use ($funcTempDir) {
    $html = '<html><head><meta name="whostyle" content="{ws2:AAAAAAA____AAAAAAD_8PDwAAAA____AIj_ERER}"></head>'
        . '<body><a href="https://mysite.com/about">Link</a></body></html>';
    $data = setupWebmentionTest($funcTempDir, $html);
    expect(isset($data[0]['whostyle']['colors']))->toBeTrue();
});

// 3. valid whostyle hash inline
it('accepts valid whostyle inline', function () use ($funcTempDir) {
    $html = '<html><body>'
        . '{ws2:AAAAAAA____AAAAAAD_8PDwAAAA____AIj_ERER}'
        . ' <a href="https://mysite.com/about">Link</a></body></html>';
    $data = setupWebmentionTest($funcTempDir, $html);
    expect(isset($data[0]['whostyle']['colors']))->toBeTrue();
});

// 4. valid whostyle hash inline and in meta (returns inline)
it('accepts valid inline and meta, preferring inline', function () use ($funcTempDir) {
    $html = '<html><head><meta name="whostyle" content="{ws2:AAAAAAA____AAAAAAD_8PDwAAAA____AIj_ERER}"></head>'
        . '<body>{ws2:AAAAAAA____AAAAAAD_8PDwAAAA____AIj_ERER}'
        . ' <a href="https://mysite.com/about">Link</a></body></html>';
    $data = setupWebmentionTest($funcTempDir, $html);
    expect(isset($data[0]['whostyle']['colors']))->toBeTrue();
});

// 5. invalid whostyle hash in meta
it('ignores meta whostyle with invalid chars', function () use ($funcTempDir) {
    $html = '<html><head><meta name="whostyle" content="{ws2:A$AAAAA____AAAAAAD_8PDwAAAA____AIj_ERER}"></head>'
        . '<body><a href="https://mysite.com/about">Link</a></body></html>';
    $data = setupWebmentionTest($funcTempDir, $html);
    expect(empty($data[0]['whostyle']))->toBeTrue();
});

it('parses meta whostyle with out-of-bounds values', function () use ($funcTempDir) {
    $html = '<html><head><meta name="whostyle" content="{ws2:oPIfBJa____AAAAAAD_8PDwAAAA____AIj_ERER}"></head>'
        . '<body><a href="https://mysite.com/about">Link</a></body></html>';
    $data = setupWebmentionTest($funcTempDir, $html);
    expect(isset($data[0]['whostyle']))->toBeTrue();
});

it('parses meta whostyle with invalid WCAG contrast', function () use ($funcTempDir) {
    $html = '<html><head><meta name="whostyle" content="{ws2:AAAAAAA____3d3dAAD_8PDwAAAA____AIj_ERER}"></head>'
        . '<body><a href="https://mysite.com/about">Link</a></body></html>';
    $data = setupWebmentionTest($funcTempDir, $html);
    expect(isset($data[0]['whostyle']))->toBeTrue();
});

// 6. invalid whostyle hash inline
it('ignores inline whostyle with invalid chars', function () use ($funcTempDir) {
    $html = '<html><body>'
        . '{ws2:A$AAAAA____AAAAAAD_8PDwAAAA____AIj_ERER}'
        . ' <a href="https://mysite.com/about">Link</a></body></html>';
    $data = setupWebmentionTest($funcTempDir, $html);
    expect(empty($data[0]['whostyle']))->toBeTrue();
});

it('parses inline whostyle with out-of-bounds values', function () use ($funcTempDir) {
    $html = '<html><body>'
        . '{ws2:oPIfBJa____AAAAAAD_8PDwAAAA____AIj_ERER}'
        . ' <a href="https://mysite.com/about">Link</a></body></html>';
    $data = setupWebmentionTest($funcTempDir, $html);
    expect(isset($data[0]['whostyle']))->toBeTrue();
});

it('parses inline whostyle with invalid WCAG contrast', function () use ($funcTempDir) {
    $html = '<html><body>'
        . '{ws2:AAAAAAA____3d3dAAD_8PDwAAAA____AIj_ERER}'
        . ' <a href="https://mysite.com/about">Link</a></body></html>';
    $data = setupWebmentionTest($funcTempDir, $html);
    expect(isset($data[0]['whostyle']))->toBeTrue();
});

// 7. invalid inline and valid meta -> returns meta
it('falls back to meta if inline has invalid chars', function () use ($funcTempDir) {
    $html = '<html><head><meta name="whostyle" content="{ws2:AAAAAAA____AAAAAAD_8PDwAAAA____AIj_ERER}"></head>'
        . '<body>{ws2:A$AAAAA____AAAAAAD_8PDwAAAA____AIj_ERER}'
        . ' <a href="https://mysite.com/about">Link</a></body></html>';
    $data = setupWebmentionTest($funcTempDir, $html);
    expect(isset($data[0]['whostyle']['colors']))->toBeTrue();
});

it('falls back to meta if inline has invalid values', function () use ($funcTempDir) {
    $html = '<html><head><meta name="whostyle" content="{ws2:AAAAAAA____AAAAAAD_8PDwAAAA____AIj_ERER}"></head>'
        . '<body>{ws2:oPIfBJa____AAAAAAD_8PDwAAAA____AIj_ERER}'
        . ' <a href="https://mysite.com/about">Link</a></body></html>';
    $data = setupWebmentionTest($funcTempDir, $html);
    expect(isset($data[0]['whostyle']['colors']))->toBeTrue();
});

it('falls back to meta if inline has invalid WCAG', function () use ($funcTempDir) {
    $html = '<html><head><meta name="whostyle" content="{ws2:AAAAAAA____AAAAAAD_8PDwAAAA____AIj_ERER}"></head>'
        . '<body>{ws2:AAAAAAA____3d3dAAD_8PDwAAAA____AIj_ERER}'
        . ' <a href="https://mysite.com/about">Link</a></body></html>';
    $data = setupWebmentionTest($funcTempDir, $html);
    expect(isset($data[0]['whostyle']['colors']))->toBeTrue();
});
