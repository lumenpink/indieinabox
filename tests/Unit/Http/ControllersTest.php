<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Indieinabox\ActivityPubHandler;
use Indieinabox\ArchiveHandler;
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
use Indieinabox\IndieAuthHandler;
use Indieinabox\MicropubClientHandler;
use Indieinabox\MicropubHandler;
use Indieinabox\MicrosubHandler;
use Indieinabox\MicrosubReaderHandler;
use Indieinabox\ModerationHandler;
use Indieinabox\Site;
use Indieinabox\WebmentionHandler;

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

    Container::getInstance()->flush();
    Container::getInstance()->instance(Site::class, $this->site);
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    Container::getInstance()->flush();
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('ActivityPubController delegates methods to handler', function () {
    $mockHandler = new class($this->site) extends ActivityPubHandler {
        public array $called = [];
        public function __construct(Site $site) { parent::__construct($site); }
        public function handleInteract(): void { $this->called[] = 'interact'; }
        public function handleAuthorizeInteraction(): void { $this->called[] = 'auth'; }
        public function handleWebFinger(): void { $this->called[] = 'webfinger'; }
        public function handleActor(): void { $this->called[] = 'actor'; }
        public function handleInbox(): void { $this->called[] = 'inbox'; }
        public function handleOutbox(): void { $this->called[] = 'outbox'; }
    };

    $controller = new ActivityPubController($this->site, $mockHandler);

    $controller->interact();
    $controller->authorizeInteraction();
    $controller->webfinger();
    $controller->actor();
    $controller->inbox();
    $controller->outbox();

    expect($mockHandler->called)->toBe(['interact', 'auth', 'webfinger', 'actor', 'inbox', 'outbox']);
});

test('MicropubController delegates endpoint and client actions', function () {
    $mockServer = new class($this->site) extends MicropubHandler {
        public bool $called = false;
        public function __construct(Site $site) { parent::__construct($site); }
        public function handle(): void { $this->called = true; }
    };

    $mockClient = new class($this->site) extends MicropubClientHandler {
        public bool $called = false;
        public function __construct(Site $site) { parent::__construct($site); }
        public function handle(): void { $this->called = true; }
    };

    $controller = new MicropubController($this->site, $mockServer, $mockClient);

    $controller->handle();
    $controller->client();

    expect($mockServer->called)->toBeTrue();
    expect($mockClient->called)->toBeTrue();
});

test('MicrosubController delegates API and reader actions', function () {
    $mockServer = new class($this->site) extends MicrosubHandler {
        public bool $called = false;
        public function __construct(Site $site) { parent::__construct($site); }
        public function handle(): void { $this->called = true; }
    };

    $mockReader = new class($this->site) extends MicrosubReaderHandler {
        public bool $called = false;
        public function __construct(Site $site) { parent::__construct($site); }
        public function handle(): void { $this->called = true; }
    };

    $controller = new MicrosubController($this->site, $mockServer, $mockReader);

    $controller->handle();
    $controller->reader();

    expect($mockServer->called)->toBeTrue();
    expect($mockReader->called)->toBeTrue();
});

test('WebmentionController, IndieAuthController, and ConfigController delegate cleanly', function () {
    $mockWm = new class($this->site) extends WebmentionHandler {
        public bool $called = false;
        public function __construct(Site $site) { parent::__construct($site); }
        public function handle(): void { $this->called = true; }
    };

    $mockAuth = new class($this->site) extends IndieAuthHandler {
        public bool $called = false;
        public function __construct(Site $site) { parent::__construct($site); }
        public function handle(): void { $this->called = true; }
    };

    $mockConfig = new class($this->site) extends ConfigHandler {
        public bool $called = false;
        public function __construct(Site $site) { parent::__construct($site); }
        public function handle(): void { $this->called = true; }
    };

    $wmCtrl = new WebmentionController($this->site, $mockWm);
    $authCtrl = new IndieAuthController($this->site, $mockAuth);
    $cfgCtrl = new ConfigController($this->site, $mockConfig);

    $wmCtrl->handle();
    $authCtrl->handle();
    $cfgCtrl->handle();

    expect($mockWm->called)->toBeTrue();
    expect($mockAuth->called)->toBeTrue();
    expect($mockConfig->called)->toBeTrue();
});

test('ArchiveController delegates handle and force snapshots', function () {
    $mockArchive = new class($this->site) extends ArchiveHandler {
        public array $called = [];
        public function __construct(Site $site) { parent::__construct($site); }
        public function handle(): void { $this->called[] = 'handle'; }
        public function handleForce(): void { $this->called[] = 'force'; }
    };

    $controller = new ArchiveController($this->site, $mockArchive);
    $controller->handle();
    $controller->force();

    expect($mockArchive->called)->toBe(['handle', 'force']);
});

test('AdminController dispatches sub-actions', function () {
    $mockCfg = new class($this->site) extends ConfigHandler {
        public bool $called = false;
        public function __construct(Site $site) { parent::__construct($site); }
        public function handle(): void { $this->called = true; }
    };
    $mockClient = new class($this->site) extends MicropubClientHandler {
        public bool $called = false;
        public function __construct(Site $site) { parent::__construct($site); }
        public function handle(): void { $this->called = true; }
    };
    $mockReader = new class($this->site) extends MicrosubReaderHandler {
        public bool $called = false;
        public function __construct(Site $site) { parent::__construct($site); }
        public function handle(): void { $this->called = true; }
    };
    $mockMod = new class($this->site) extends ModerationHandler {
        public bool $called = false;
        public function __construct(Site $site) { parent::__construct($site); }
        public function handle(): void { $this->called = true; }
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
