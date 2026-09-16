<?php

declare(strict_types=1);

namespace Tests\Functional;

use Indieinabox\Http\StaticFileServer;
use Indieinabox\Site\Site;
use Indieinabox\Site\Paths;
use Indieinabox\Support\FileUtils;
use Indieinabox\Http\WebRouter;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_static_workflow_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/public_html', 0777, true);
    mkdir($this->tempDir . '/public_html/assets', 0777, true);
    mkdir($this->tempDir . '/content/media', 0777, true);

    \Indieinabox\Core\Database::disconnect();
    \Indieinabox\Core\Database::$dataDir = $this->tempDir . '/data';
    \Indieinabox\Core\Database::connect(':memory:');
    $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database.sql');
    if ($sql !== '') {
        \Indieinabox\Core\Database::getDb()->exec($sql);
    }

    $paths = new Paths($this->tempDir);
    $paths->outputDirHtml = 'public_html';
    $this->site = new Site(null, $paths);

    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

afterEach(function () {
    \Indieinabox\Core\Database::disconnect();
    FileUtils::recursiveRmdir($this->tempDir);
});

it('routes static assets workflow through WebRouter and StaticFileServer', function () {
    file_put_contents($this->tempDir . '/public_html/assets/style.css', 'body { color: red; }');

    $_SERVER['REQUEST_URI'] = '/assets/style.css';

    $router = new WebRouter($this->site);

    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toBe('body { color: red; }');
});

it('routes ActivityPub content negotiation through WebRouter workflow', function () {
    file_put_contents($this->tempDir . '/public_html/note.html', '<p>A simple note</p>');
    file_put_contents($this->tempDir . '/public_html/note.json', '{"@context":"https://www.w3.org/ns/activitystreams","type":"Note"}');

    // Default request gives HTML
    $_SERVER['REQUEST_URI'] = '/note.html';
    $_SERVER['HTTP_ACCEPT'] = 'text/html';

    $router = new WebRouter($this->site);

    ob_start();
    $router->handleRequest();
    $htmlOutput = ob_get_clean();

    expect($htmlOutput)->toBe('<p>A simple note</p>');

    // ActivityPub request gives JSON
    $_SERVER['HTTP_ACCEPT'] = 'application/activity+json';

    ob_start();
    $router->handleRequest();
    $apOutput = ob_get_clean();

    expect($apOutput)->toBe('{"@context":"https://www.w3.org/ns/activitystreams","type":"Note"}');
});

it('routes content media workflow through WebRouter and StaticFileServer', function () {
    file_put_contents($this->tempDir . '/content/media/avatar.jpg', 'fake-jpg-content');

    $_SERVER['REQUEST_URI'] = '/media/avatar.jpg';

    $router = new WebRouter($this->site);

    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toBe('fake-jpg-content');
});

it('preserves API route priority over static file server fallback', function () {
    // If a static file accidentally exists named webmention, the controller route must take precedence
    file_put_contents($this->tempDir . '/public_html/webmention', 'Static webmention trap');

    $_SERVER['REQUEST_URI'] = '/webmention';

    $mockServer = new class($this->site) extends StaticFileServer {
        public bool $served = false;
        public function serve(?string $requestUri = null): void
        {
            $this->served = true;
        }
    };

    $router = new WebRouter($this->site, $mockServer);

    ob_start();
    $router->handleRequest();
    ob_end_clean();

    // The static file server must not have been invoked
    expect($mockServer->served)->toBeFalse();
});
