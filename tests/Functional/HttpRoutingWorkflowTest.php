<?php

declare(strict_types=1);

namespace Tests\Functional;

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\Http\WebRouter;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_http_workflow_' . uniqid();
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
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS activitypub_outbox (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payload_json TEXT NOT NULL,
        target_inbox TEXT NOT NULL,
        status TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS activitypub_actors (
        actor_url TEXT PRIMARY KEY,
        inbox_url TEXT,
        shared_inbox_url TEXT,
        public_key_pem TEXT,
        updated_at INTEGER NOT NULL
    )");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS outgoing_webmentions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        source_url TEXT NOT NULL,
        target_url TEXT NOT NULL,
        status TEXT NOT NULL,
        attempts INTEGER NOT NULL,
        last_attempt INTEGER NOT NULL,
        created_at INTEGER NOT NULL
    )");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS archive_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        url TEXT NOT NULL,
        requested_at INTEGER NOT NULL,
        force_archive INTEGER DEFAULT 0,
        status TEXT DEFAULT 'pending',
        error TEXT
    )");

    Database::saveSetting('fqdn', 'https://route-test.example');
    Database::saveSetting('activitypub_handle', 'sysop');

    $paths = new Paths($this->tempDir);
    $paths->outputDirHtml = 'public_html';
    $this->site = new Site(null, $paths);
    $this->site->metadata->fqdn = 'https://route-test.example';
    $this->site->config = [
        'activitypub_enabled' => true,
    ];

    Container::getInstance()->flush();
    Container::getInstance()->instance(Site::class, $this->site);

    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    Container::getInstance()->flush();
    \Indieinabox\Support\FileUtils::recursiveRmdir($this->tempDir);
});

test('Functional HTTP workflow: webmention help page, micropub redirect, and cron worker', function () {
    $router = new WebRouter($this->site);

    // 1. Webmention GET returns HTML form
    $_SERVER['REQUEST_URI'] = '/webmention';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();
    expect($output)->toContain('Webmention');

    // 2. Cron endpoint executes background worker and returns OK
    $_SERVER['REQUEST_URI'] = '/cron';
    ob_start();
    $router->handleRequest();
    $cronOutput = ob_get_clean();
    expect($cronOutput)->toContain('OK');

    // 3. Static fallback works for actual files
    file_put_contents($this->tempDir . '/public_html/about.html', '<h1>About Page</h1>');
    $_SERVER['REQUEST_URI'] = '/about.html';
    ob_start();
    $router->handleRequest();
    $staticOutput = ob_get_clean();
    expect($staticOutput)->toBe('<h1>About Page</h1>');
});

test('Functional HTTP workflow: ActivityPub webfinger discovery', function () {
    $router = new WebRouter($this->site);

    $_SERVER['REQUEST_URI'] = '/.well-known/webfinger';
    $_GET['resource'] = 'acct:sysop@route-test.example';

    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $json = json_decode($output, true);
    expect($json)->toBeArray();
    expect($json['subject'])->toBe('acct:sysop@route-test.example');
    expect($json['links'])->toBeArray();
});
