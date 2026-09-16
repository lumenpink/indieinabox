<?php

declare(strict_types=1);

use Indieinabox\Http\Controllers\ActivityPubController;
use Indieinabox\Http\Controllers\AdminController;
use Indieinabox\Http\Controllers\ArchiveController;
use Indieinabox\Http\Controllers\IndieAuthController;
use Indieinabox\Http\Controllers\MicropubController;
use Indieinabox\Http\Controllers\MicrosubController;
use Indieinabox\Http\Controllers\WebmentionController;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\Support\FileUtils;
use Indieinabox\WebRouter;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_webrouter_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/public_html', 0777, true);

    \Indieinabox\Database::disconnect();
    \Indieinabox\Database::$dataDir = $this->tempDir . '/data';
    \Indieinabox\Database::connect(':memory:');
    $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database.sql');
    \Indieinabox\Database::getDb()->exec($sql);

    $paths = new Paths($this->tempDir);
    $paths->outputDirHtml = 'public_html';
    $this->site = new Site(null, $paths);

    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

afterEach(function () {
    \Indieinabox\Database::disconnect();
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

it('dispatches webmention requests to WebmentionController', function () {
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
        public function getWebmentionController(): WebmentionController
        {
            return new class($this->site, $this->state) extends WebmentionController {
                private stdClass $state;
                public function __construct(Site $site, stdClass $state)
                {
                    parent::__construct($site);
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

it('dispatches auth requests to IndieAuthController', function () {
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
        public function getIndieAuthController(): IndieAuthController
        {
            return new class($this->site, $this->state) extends IndieAuthController {
                private stdClass $state;
                public function __construct(Site $site, stdClass $state)
                {
                    parent::__construct($site);
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

it('dispatches micropub requests to MicropubController', function () {
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
        public function getMicropubController(): MicropubController
        {
            return new class($this->site, $this->state) extends MicropubController {
                private stdClass $state;
                public function __construct(Site $site, stdClass $state)
                {
                    parent::__construct($site);
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

it('dispatches archive requests to ArchiveController', function () {
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
        public function getArchiveController(): ArchiveController
        {
            return new class($this->site, $this->state) extends ArchiveController {
                private stdClass $state;
                public function __construct(Site $site, stdClass $state)
                {
                    parent::__construct($site);
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

it('dispatches microsub requests to MicrosubController', function () {
    $_SERVER['REQUEST_URI'] = '/microsub';

    $state = new stdClass();
    $state->called = false;

    $router = new class($this->site, $state) extends WebRouter {
        private stdClass $state;
        public function __construct(Site $site, stdClass $state)
        {
            parent::__construct($site);
            $this->state = $state;
        }
        public function getMicrosubController(): MicrosubController
        {
            return new class($this->site, $this->state) extends MicrosubController {
                private stdClass $state;
                public function __construct(Site $site, stdClass $state)
                {
                    parent::__construct($site);
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

it('dispatches admin requests to AdminController', function () {
    $_SERVER['REQUEST_URI'] = '/admin/config';

    $state = new stdClass();
    $state->called = false;

    $router = new class($this->site, $state) extends WebRouter {
        private stdClass $state;
        public function __construct(Site $site, stdClass $state)
        {
            parent::__construct($site);
            $this->state = $state;
        }
        public function getAdminController(): AdminController
        {
            return new class($this->site, $this->state) extends AdminController {
                private stdClass $state;
                public function __construct(Site $site, stdClass $state)
                {
                    parent::__construct($site);
                    $this->state = $state;
                }
                public function config(): void
                {
                    $this->state->called = true;
                }
            };
        }
    };

    $router->handleRequest();
    expect($state->called)->toBeTrue();
});
