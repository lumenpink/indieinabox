<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Http\WebRouter;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_health_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/public_html', 0777, true);

    Database::disconnect();
    Database::$dataDir = $this->tempDir . '/data';
    Database::connect(':memory:');
    $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database.sql');
    Database::getDb()->exec($sql);

    $paths = new Paths($this->tempDir);
    $paths->outputDirHtml = 'public_html';
    $this->site = new Site(null, $paths);

    $_GET = [];
    $_POST = [];
    $_SERVER = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
});

afterEach(function () {
    Database::disconnect();
    FileUtils::recursiveRmdir($this->tempDir);
});

it('returns healthy status code and JSON payload on /health and /healthz', function () {
    $router = new WebRouter($this->site);

    $_SERVER['REQUEST_URI'] = '/health';
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    $data = json_decode($output, true);
    expect($data)->toBeArray();
    expect($data['status'])->toBe('ok');
    expect($data['database'])->toBe('connected');
    expect($data)->toHaveKey('version');
    expect($data)->toHaveKey('timestamp');

    // Test /healthz alias
    $_SERVER['REQUEST_URI'] = '/healthz';
    ob_start();
    $router->handleRequest();
    $outputZ = ob_get_clean();

    $dataZ = json_decode($outputZ, true);
    expect($dataZ)->toBeArray();
    expect($dataZ['status'])->toBe('ok');
});
