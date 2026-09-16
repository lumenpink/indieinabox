<?php

declare(strict_types=1);

use Indieinabox\Http\StaticFileServer;
use Indieinabox\Site\Site;
use Indieinabox\Site\Paths;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_static_server_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/public_html', 0777, true);
    mkdir($this->tempDir . '/content/media', 0777, true);

    $paths = new Paths($this->tempDir);
    $paths->outputDirHtml = 'public_html';
    $this->site = new Site(null, $paths);
    $this->server = new StaticFileServer($this->site);

    $_SERVER = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

afterEach(function () {
    FileUtils::recursiveRmdir($this->tempDir);
});

it('resolves correct MIME types for known and unknown extensions', function () {
    expect($this->server->getMimeType('html'))->toBe('text/html; charset=utf-8');
    expect($this->server->getMimeType('css'))->toBe('text/css; charset=utf-8');
    expect($this->server->getMimeType('js'))->toBe('application/javascript; charset=utf-8');
    expect($this->server->getMimeType('png'))->toBe('image/png');
    expect($this->server->getMimeType('jpg'))->toBe('image/jpeg');
    expect($this->server->getMimeType('jpeg'))->toBe('image/jpeg');
    expect($this->server->getMimeType('gif'))->toBe('image/gif');
    expect($this->server->getMimeType('svg'))->toBe('image/svg+xml');
    expect($this->server->getMimeType('xml'))->toBe('application/xml; charset=utf-8');
    expect($this->server->getMimeType('json'))->toBe('application/json; charset=utf-8');
    expect($this->server->getMimeType('txt'))->toBe('text/plain; charset=utf-8');
    expect($this->server->getMimeType('gmi'))->toBe('text/gemini; charset=utf-8');
    expect($this->server->getMimeType('xyz'))->toBe('application/octet-stream');
});

it('serves an existing file from the output directory', function () {
    file_put_contents($this->tempDir . '/public_html/hello.txt', 'Static Content Here');

    $_SERVER['REQUEST_URI'] = '/hello.txt';

    ob_start();
    $this->server->serve();
    $output = ob_get_clean();

    expect($output)->toBe('Static Content Here');
});

it('serves directory index.html when root or directory is requested', function () {
    file_put_contents($this->tempDir . '/public_html/index.html', '<h1>Welcome</h1>');

    $_SERVER['REQUEST_URI'] = '/';

    ob_start();
    $this->server->serve();
    $output = ob_get_clean();

    expect($output)->toBe('<h1>Welcome</h1>');

    mkdir($this->tempDir . '/public_html/subfolder', 0777, true);
    file_put_contents($this->tempDir . '/public_html/subfolder/index.html', '<p>Sub Index</p>');

    $_SERVER['REQUEST_URI'] = '/subfolder';

    ob_start();
    $this->server->serve();
    $subOutput = ob_get_clean();

    expect($subOutput)->toBe('<p>Sub Index</p>');
});

it('performs content negotiation for ActivityPub requests', function () {
    file_put_contents($this->tempDir . '/public_html/article.html', '<article>Article HTML</article>');
    file_put_contents($this->tempDir . '/public_html/article.json', '{"type":"Article","name":"Title"}');

    // Standard HTML request
    $_SERVER['REQUEST_URI'] = '/article.html';
    $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';

    ob_start();
    $this->server->serve();
    $htmlOutput = ob_get_clean();

    expect($htmlOutput)->toBe('<article>Article HTML</article>');

    // ActivityPub request
    $_SERVER['HTTP_ACCEPT'] = 'application/activity+json, application/ld+json';

    ob_start();
    $this->server->serve();
    $apOutput = ob_get_clean();

    expect($apOutput)->toBe('{"type":"Article","name":"Title"}');
});

it('serves media from content directory fallback when path starts with /media/', function () {
    file_put_contents($this->tempDir . '/content/media/photo.png', 'fake-png-binary-data');

    $_SERVER['REQUEST_URI'] = '/media/photo.png';

    ob_start();
    $this->server->serve();
    $output = ob_get_clean();

    expect($output)->toBe('fake-png-binary-data');
});

it('outputs 404 response when file is not found', function () {
    $_SERVER['REQUEST_URI'] = '/non-existent-file.txt';

    ob_start();
    $this->server->serve();
    $output = ob_get_clean();

    expect($output)->toContain('404 Not Found')
        ->toContain('non-existent-file.txt');
});
