<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Indieinabox\ConfigHandler;
use Indieinabox\Core\Container;
use Indieinabox\Database;
use Indieinabox\Http\Controllers\ActivityPubController;
use Indieinabox\Http\Controllers\AdminController;
use Indieinabox\Http\Controllers\ArchiveController;
use Indieinabox\Http\Controllers\ConfigController;
use Indieinabox\Http\Controllers\IndieAuthController;
use Indieinabox\Http\Controllers\MicropubController;
use Indieinabox\Http\Controllers\MicrosubController;
use Indieinabox\Http\Controllers\WebmentionController;
use Indieinabox\MicropubClientHandler;
use Indieinabox\MicrosubHandler;
use Indieinabox\MicrosubReaderHandler;
use Indieinabox\ModerationHandler;
use Indieinabox\Services\ArchiveService;
use Indieinabox\Services\WebmentionService;
use Indieinabox\Site;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_ctrl_test_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS activitypub_keys (key_id TEXT PRIMARY KEY, private_key TEXT NOT NULL, public_key TEXT NOT NULL, created_at INTEGER NOT NULL)");

    $this->site = new Site();
    $this->site->metadata->fqdn = 'https://controllers.example';

    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    $_SESSION = [];
    $_FILES = [];
    http_response_code(200);

    Container::getInstance()->flush();
    Container::getInstance()->instance(Site::class, $this->site);
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    Container::getInstance()->flush();
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('ActivityPubController executes endpoint methods cleanly', function () {
    $controller = new ActivityPubController($this->site);

    $_GET['resource'] = 'acct:test@controllers.example';
    ob_start();
    $controller->webfinger();
    $wfOutput = ob_get_clean();
    expect($wfOutput)->toBeJson();

    ob_start();
    $controller->actor();
    $actorOutput = ob_get_clean();
    expect($actorOutput)->toBeJson();

    ob_start();
    $controller->outbox();
    $obOutput = ob_get_clean();
    expect($obOutput)->toBeJson();
});

test('MicropubController delegates endpoint and client actions', function () {
    $mockClient = new class($this->site) extends MicropubClientHandler {
        public bool $called = false;
        public function __construct(Site $site)
        {
            parent::__construct($site);
        }
        public function handle(): void
        {
            $this->called = true;
        }
    };

    $controller = new MicropubController($this->site, null, $mockClient);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['q'] = 'config';
    ob_start();
    $controller->handle();
    $output = ob_get_clean();

    expect(http_response_code())->toBe(401); // Requires auth

    $controller->client();
    expect($mockClient->called)->toBeTrue();
});

test('MicrosubController delegates API and reader actions', function () {
    $mockServer = new class($this->site) extends MicrosubHandler {
        public bool $called = false;
        public function __construct(Site $site)
        {
            parent::__construct($site);
        }
        public function handle(): void
        {
            $this->called = true;
        }
    };

    $mockReader = new class($this->site) extends MicrosubReaderHandler {
        public bool $called = false;
        public function __construct(Site $site)
        {
            parent::__construct($site);
        }
        public function handle(): void
        {
            $this->called = true;
        }
    };

    $controller = new MicrosubController($this->site, $mockServer, $mockReader);

    $controller->handle();
    $controller->reader();

    expect($mockServer->called)->toBeTrue();
    expect($mockReader->called)->toBeTrue();
});

test('WebmentionController, IndieAuthController, and ConfigController handle actions cleanly', function () {
    $mockWmService = new class extends WebmentionService {
        public bool $queued = false;
        public function __construct()
        {
        }
        public function queue(string $source, string $target): bool
        {
            $this->queued = true;
            return true;
        }
        public function isValidTarget(string $target, Site $site): bool
        {
            return true;
        }
    };

    $wmCtrl = new WebmentionController($this->site, $mockWmService);
    $authCtrl = new IndieAuthController($this->site);
    $cfgCtrl = new ConfigController($this->site);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    $wmCtrl->handle();
    $wmOutput = ob_get_clean();
    expect($wmOutput)->toContain('Webmention Endpoint');

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/.well-known/oauth-authorization-server';
    ob_start();
    $authCtrl->handle();
    $authOutput = ob_get_clean();
    expect($authOutput)->toContain('authorization_endpoint');
});

test('ArchiveController handles snapshots and force updates', function () {
    $mockArchive = new class extends ArchiveService {
        public array $called = [];
        public function __construct()
        {
        }
        public function findSnapshot(string $url, ?int $timestamp = null): ?array
        {
            $this->called[] = 'find';
            return null;
        }
        public function queueForceArchive(string $url): bool
        {
            $this->called[] = 'force';
            return true;
        }
    };

    $controller = new ArchiveController($this->site, $mockArchive);

    $_GET['url'] = 'https://example.com';
    ob_start();
    $controller->handle();
    $output = ob_get_clean();
    expect($output)->toContain('Archive View');

    $_POST['url'] = 'https://example.com';
    ob_start();
    $controller->force();
    ob_end_clean();

    expect($mockArchive->called)->toBe(['find', 'force']);
});

test('AdminController dispatches sub-actions', function () {
    $mockCfg = new class($this->site) extends ConfigHandler {
        public bool $called = false;
        public function __construct(Site $site)
        {
            parent::__construct($site);
        }
        public function handle(): void
        {
            $this->called = true;
        }
    };
    $mockClient = new class($this->site) extends MicropubClientHandler {
        public bool $called = false;
        public function __construct(Site $site)
        {
            parent::__construct($site);
        }
        public function handle(): void
        {
            $this->called = true;
        }
    };
    $mockReader = new class($this->site) extends MicrosubReaderHandler {
        public bool $called = false;
        public function __construct(Site $site)
        {
            parent::__construct($site);
        }
        public function handle(): void
        {
            $this->called = true;
        }
    };
    $mockMod = new class($this->site) extends ModerationHandler {
        public bool $called = false;
        public function __construct(Site $site)
        {
            parent::__construct($site);
        }
        public function handle(): void
        {
            $this->called = true;
        }
    };

    $admin = new AdminController($this->site, $mockCfg, $mockClient, $mockReader, $mockMod);

    $admin->config();
    $admin->micropub();
    $admin->microsub();
    $admin->moderation();

    expect($mockCfg->called)->toBeTrue();
    expect($mockClient->called)->toBeTrue();
    expect($mockReader->called)->toBeTrue();
    expect($mockMod->called)->toBeTrue();
});
