<?php

declare(strict_types=1);

use Indieinabox\ActivityPubHandler;
use Indieinabox\ArchiveHandler;
use Indieinabox\Support\FileUtils;
use Indieinabox\IndieAuthHandler;
use Indieinabox\MicropubClientHandler;
use Indieinabox\MicropubHandler;
use Indieinabox\MicrosubHandler;
use Indieinabox\MicrosubReaderHandler;
use Indieinabox\ModerationHandler;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\WebmentionHandler;
use Indieinabox\WebRouter;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_webrouter_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/public_html', 0777, true);

    $paths = new Paths($this->tempDir);
    $paths->outputDirHtml = 'public_html';
    $this->site = new Site(null, $paths);

    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

afterEach(function () {
    FileUtils::recursiveRmdir($this->tempDir);
});

it('resolves correct MIME types for common web extensions', function () {
    $router = new WebRouter($this->site);

    expect($router->getMimeType('html'))->toBe('text/html; charset=utf-8');
    expect($router->getMimeType('css'))->toBe('text/css; charset=utf-8');
    expect($router->getMimeType('js'))->toBe('application/javascript; charset=utf-8');
    expect($router->getMimeType('png'))->toBe('image/png');
    expect($router->getMimeType('json'))->toBe('application/json; charset=utf-8');
    expect($router->getMimeType('xml'))->toBe('application/xml; charset=utf-8');
    expect($router->getMimeType('gmi'))->toBe('text/gemini; charset=utf-8');
    expect($router->getMimeType('unknown_ext'))->toBe('application/octet-stream');
});

it('serves static files from public directory', function () {
    file_put_contents($this->tempDir . '/public_html/test.txt', 'Hello Static');

    $_SERVER['REQUEST_URI'] = '/test.txt';

    $router = new WebRouter($this->site);

    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toBe('Hello Static');
});

it('returns 404 when static file is not found', function () {
    $_SERVER['REQUEST_URI'] = '/missing-page';

    $router = new WebRouter($this->site);

    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toContain('404 Not Found');
});

it('dispatches webmention requests to WebmentionHandler', function () {
    $_SERVER['REQUEST_URI'] = '/webmention';

    $state = new stdClass();
    $state->called = false;

    $router = new class($this->site, $state) extends WebRouter {
        private stdClass $state;
        public function __construct(Site $site, stdClass $state)
        {
            parent::__construct($site);
            $this->state = $state;
        }
        protected function createWebmentionHandler(): WebmentionHandler
        {
            return new class($this->site, $this->state) extends WebmentionHandler {
                private stdClass $state;
                public function __construct(Site $site, stdClass $state)
                {
                    $this->state = $state;
                }
                public function handle(): void
                {
                    $this->state->called = true;
                }
            };
        }
    };

    $router->handleRequest();
    expect($state->called)->toBeTrue();
});

it('dispatches auth requests to IndieAuthHandler', function () {
    $_SERVER['REQUEST_URI'] = '/auth';

    $state = new stdClass();
    $state->called = false;

    $router = new class($this->site, $state) extends WebRouter {
        private stdClass $state;
        public function __construct(Site $site, stdClass $state)
        {
            parent::__construct($site);
            $this->state = $state;
        }
        protected function createIndieAuthHandler(): IndieAuthHandler
        {
            return new class($this->site, $this->state) extends IndieAuthHandler {
                private stdClass $state;
                public function __construct(Site $site, stdClass $state)
                {
                    $this->state = $state;
                }
                public function handle(): void
                {
                    $this->state->called = true;
                }
            };
        }
    };

    $router->handleRequest();
    expect($state->called)->toBeTrue();
});

it('dispatches micropub requests to MicropubHandler', function () {
    $_SERVER['REQUEST_URI'] = '/micropub';

    $state = new stdClass();
    $state->called = false;

    $router = new class($this->site, $state) extends WebRouter {
        private stdClass $state;
        public function __construct(Site $site, stdClass $state)
        {
            parent::__construct($site);
            $this->state = $state;
        }
        protected function createMicropubHandler(): MicropubHandler
        {
            return new class($this->site, $this->state) extends MicropubHandler {
                private stdClass $state;
                public function __construct(Site $site, stdClass $state)
                {
                    $this->state = $state;
                }
                public function handle(): void
                {
                    $this->state->called = true;
                }
            };
        }
    };

    $router->handleRequest();
    expect($state->called)->toBeTrue();
});

it('dispatches archive requests to ArchiveHandler', function () {
    $_SERVER['REQUEST_URI'] = '/archive';

    $state = new stdClass();
    $state->called = false;

    $router = new class($this->site, $state) extends WebRouter {
        private stdClass $state;
        public function __construct(Site $site, stdClass $state)
        {
            parent::__construct($site);
            $this->state = $state;
        }
        protected function createArchiveHandler(): ArchiveHandler
        {
            return new class($this->site, $this->state) extends ArchiveHandler {
                private stdClass $state;
                public function __construct(Site $site, stdClass $state)
                {
                    $this->state = $state;
                }
                public function handle(): void
                {
                    $this->state->called = true;
                }
            };
        }
    };

    $router->handleRequest();
    expect($state->called)->toBeTrue();
});
