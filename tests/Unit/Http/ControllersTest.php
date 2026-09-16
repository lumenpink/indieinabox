<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

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
    $sql = (string) file_get_contents(dirname(__DIR__, 3) . '/database.sql');
    Database::getDb()->exec($sql);

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
    $controller = new MicropubController($this->site);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['q'] = 'config';
    ob_start();
    $controller->handle();
    $output = ob_get_clean();

    expect(http_response_code())->toBe(401); // Requires auth

    $_SESSION['admin_authenticated'] = true;
    ob_start();
    $controller->client();
    $clientHtml = ob_get_clean();
    expect($clientHtml)->toContain('Indieinabox Publisher');
});

test('MicrosubController delegates API and reader actions', function () {
    $controller = new MicrosubController($this->site);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_REQUEST['action'] = 'channels';
    ob_start();
    $controller->handle();
    $output = ob_get_clean();
    expect(http_response_code())->toBe(401); // Unauthorized

    $_SESSION['admin_authenticated'] = true;
    ob_start();
    $controller->handle();
    $channelsJson = ob_get_clean();
    expect($channelsJson)->toBeJson();

    ob_start();
    $controller->reader();
    $readerHtml = ob_get_clean();
    expect($readerHtml)->toContain('Timeline');
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
    $admin = new AdminController($this->site);

    // Unauthenticated config renders bootstrap if no password exists
    ob_start();
    $admin->config();
    $bootstrapHtml = ob_get_clean();
    expect($bootstrapHtml)->toContain('IndieAuth Password');

    $_SESSION['admin_authenticated'] = true;

    ob_start();
    $admin->micropub();
    $pubHtml = ob_get_clean();
    expect($pubHtml)->toContain('Indieinabox Publisher');

    ob_start();
    $admin->microsub();
    $subHtml = ob_get_clean();
    expect($subHtml)->toContain('Timeline');

    ob_start();
    $admin->moderation();
    $modHtml = ob_get_clean();
    expect($modHtml)->toContain('Comment Moderation');

    ob_start();
    $admin->cron();
    $cronOutput = ob_get_clean();
    expect($cronOutput)->toContain('OK');
});
