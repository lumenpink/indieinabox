<?php

declare(strict_types=1);

namespace Tests\Integration;

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Http\Controllers\ActivityPubController;
use Indieinabox\Http\Controllers\AdminController;
use Indieinabox\Http\Controllers\ArchiveController;
use Indieinabox\Http\Controllers\ConfigController;
use Indieinabox\Http\Controllers\IndieAuthController;
use Indieinabox\Http\Controllers\MicropubController;
use Indieinabox\Http\Controllers\MicrosubController;
use Indieinabox\Http\Controllers\WebmentionController;
use Indieinabox\Http\WebRouter;
use Indieinabox\Services\ModerationService;
use Indieinabox\Services\WebmentionService;
use Indieinabox\Site\Site;
use Indieinabox\Site\Paths;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_ctrl_integ_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/data');
    mkdir($this->tempDir . '/public_html', 0777, true);

    Database::$dataDir = $this->tempDir . '/data';
    $dbPath = Database::$dataDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS inbox_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        payload_json TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS activitypub_keys (
        key_id TEXT PRIMARY KEY,
        private_key TEXT NOT NULL,
        public_key TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )");

    Database::saveSetting('fqdn', 'https://integ-ctrl.example');

    $paths = new Paths($this->tempDir);
    $paths->outputDirHtml = 'public_html';
    $this->site = new Site(null, $paths);
    $this->site->metadata->fqdn = 'https://integ-ctrl.example';

    $container = Container::getInstance();
    $container->flush();
    $container->instance(Site::class, $this->site);
});

afterEach(function () {
    Container::getInstance()->flush();
    Database::disconnect();
    Database::$dataDir = '';
    \Indieinabox\Support\FileUtils::recursiveRmdir($this->tempDir);
});

test('Integration: DI container autowires all HTTP Controllers and domain Services', function () {
    $container = Container::getInstance();

    expect($container->make(ActivityPubController::class))->toBeInstanceOf(ActivityPubController::class);
    expect($container->make(WebmentionController::class))->toBeInstanceOf(WebmentionController::class);
    expect($container->make(IndieAuthController::class))->toBeInstanceOf(IndieAuthController::class);
    expect($container->make(MicropubController::class))->toBeInstanceOf(MicropubController::class);
    expect($container->make(MicrosubController::class))->toBeInstanceOf(MicrosubController::class);
    expect($container->make(AdminController::class))->toBeInstanceOf(AdminController::class);
    expect($container->make(ArchiveController::class))->toBeInstanceOf(ArchiveController::class);
    expect($container->make(ConfigController::class))->toBeInstanceOf(ConfigController::class);
    expect($container->make(WebmentionService::class))->toBeInstanceOf(WebmentionService::class);
    expect($container->make(ModerationService::class))->toBeInstanceOf(ModerationService::class);
});

test('Integration: WebmentionController receives POST and inserts into SQLite inbox_queue', function () {
    // Create target on disk
    mkdir($this->tempDir . '/public_html/notes/post-1', 0777, true);
    file_put_contents($this->tempDir . '/public_html/notes/post-1/index.html', '<h1>My Post</h1>');

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['source'] = 'https://other-blog.com/reply-1';
    $_POST['target'] = 'https://integ-ctrl.example/notes/post-1';

    $controller = new WebmentionController($this->site);

    ob_start();
    $controller->handle();
    $output = ob_get_clean();

    $response = json_decode($output, true);
    expect($response['status'])->toBe(202);

    $stmt = Database::getDb()->query("SELECT type, payload_json FROM inbox_queue WHERE type = 'webmention'");
    $queued = $stmt->fetch(\PDO::FETCH_ASSOC);
    expect($queued)->not->toBeFalse();

    $payload = json_decode($queued['payload_json'], true);
    expect($payload['source'])->toBe('https://other-blog.com/reply-1');
    expect($payload['target'])->toBe('https://integ-ctrl.example/notes/post-1');
});
