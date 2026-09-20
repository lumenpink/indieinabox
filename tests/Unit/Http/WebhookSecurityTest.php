<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Http\Controllers\AdminController;
use Indieinabox\Http\WebRouter;
use Indieinabox\Site\Site;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_webhook_test_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/content');
    mkdir($this->tempDir . '/public_html');
    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    $sql = (string) file_get_contents(dirname(__DIR__, 3) . '/database.sql');
    Database::getDb()->exec($sql);

    $this->site = new Site();
    $this->site->paths->baseDir = $this->tempDir;
    $this->site->paths->contentDir = 'content';
    $this->site->paths->outputDirHtml = 'public_html';
    $this->site->metadata->fqdn = 'https://webhook.example';

    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    $_SESSION = [];
    http_response_code(200);

    Container::getInstance()->flush();
    Container::getInstance()->instance(Site::class, $this->site);
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    Container::getInstance()->flush();
    putenv('CRON_TOKEN');
    putenv('BUILD_TOKEN');
    putenv('WEBHOOK_TOKEN');
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('cron endpoint blocks non-local access when no token is configured', function () {
    $controller = new AdminController($this->site);
    $_SERVER['REMOTE_ADDR'] = '198.51.100.25';

    ob_start();
    $controller->cron();
    $output = ob_get_clean();

    expect(http_response_code())->toBe(403);
    $data = json_decode($output, true);
    expect($data)->toBeArray()
        ->and($data['error'])->toContain('Forbidden');
});

test('cron endpoint allows local access when no token is configured', function () {
    $controller = new AdminController($this->site);
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    ob_start();
    $controller->cron();
    $output = ob_get_clean();

    expect(http_response_code())->toBe(200)
        ->and($output)->toContain('OK');
});

test('cron endpoint enforces token validation when token is configured', function () {
    Database::saveSetting('cron_token', 'my_cron_secret');
    $this->site->config['cron_token'] = 'my_cron_secret';
    $controller = new AdminController($this->site);
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

    // 1. Missing token
    ob_start();
    $controller->cron();
    $output = ob_get_clean();
    expect(http_response_code())->toBe(401);

    // 2. Wrong token
    $_GET['token'] = 'wrong_token';
    ob_start();
    $controller->cron();
    $output = ob_get_clean();
    expect(http_response_code())->toBe(401);

    // 3. Correct token via query parameter
    $_GET['token'] = 'my_cron_secret';
    ob_start();
    $controller->cron();
    $output = ob_get_clean();
    expect(http_response_code())->toBe(200)
        ->and($output)->toContain('OK');

    // 4. Correct token via Authorization Bearer header
    $_GET = [];
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer my_cron_secret';
    ob_start();
    $controller->cron();
    $output = ob_get_clean();
    expect(http_response_code())->toBe(200)
        ->and($output)->toContain('OK');

    // 5. Correct token via X-Cron-Token header
    $_SERVER['HTTP_AUTHORIZATION'] = '';
    $_SERVER['HTTP_X_CRON_TOKEN'] = 'my_cron_secret';
    ob_start();
    $controller->cron();
    $output = ob_get_clean();
    expect(http_response_code())->toBe(200)
        ->and($output)->toContain('OK');
});

test('build endpoint blocks unauthenticated calls and executes rebuild on valid token', function () {
    Database::saveSetting('build_token', 'build_secret_456');
    $this->site->config['build_token'] = 'build_secret_456';
    $controller = new AdminController($this->site);

    // 1. Missing token -> 401
    ob_start();
    $controller->build();
    $output = ob_get_clean();
    expect(http_response_code())->toBe(401);

    // 2. Valid token synchronous rebuild -> 200
    $_GET['token'] = 'build_secret_456';
    ob_start();
    $controller->build();
    $output = ob_get_clean();

    expect(http_response_code())->toBe(200);
    $data = json_decode($output, true);
    expect($data['status'])->toBe(200)
        ->and($data['message'])->toContain('Site rebuilt successfully')
        ->and($data)->toHaveKey('duration_ms');

    // 3. Valid token async rebuild -> 202 queued
    $_GET['async'] = '1';
    ob_start();
    $controller->build();
    $output = ob_get_clean();

    expect(http_response_code())->toBe(202);
    $data = json_decode($output, true);
    expect($data['status'])->toBe(202)
        ->and($data['message'])->toContain('queued');

    $stmt = Database::getDb()->query("SELECT COUNT(*) FROM inbox_queue WHERE type = 'build_site'");
    expect((int) $stmt->fetchColumn())->toBe(1);
});

test('WebRouter dispatches /build route to AdminController', function () {
    Database::saveSetting('build_token', 'test_build_token');
    $this->site->config['build_token'] = 'test_build_token';

    $_SERVER['REQUEST_URI'] = '/build?token=test_build_token&async=1';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET['token'] = 'test_build_token';
    $_GET['async'] = '1';

    $router = new WebRouter($this->site);

    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    expect(http_response_code())->toBe(202);
    $data = json_decode($output, true);
    expect($data['status'])->toBe(202);
});
