<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Http\WebRouter;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_client_controller_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/content', 0777, true);
    mkdir($this->tempDir . '/public_html', 0777, true);

    Database::disconnect();
    Database::$dataDir = $this->tempDir . '/data';
    Database::connect(':memory:');
    $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database.sql');
    Database::getDb()->exec($sql);

    $paths = new Paths($this->tempDir);
    $paths->contentDir = $this->tempDir . '/content';
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

it('renders the unified client UI on /client and /reader', function () {
    $router = new WebRouter($this->site);

    $_SERVER['REQUEST_URI'] = '/client';
    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toContain('<!DOCTYPE html>');
    expect($output)->toContain('Unified Client');
    expect($output)->toContain('id="channelsList"');
    expect($output)->toContain('id="composerModal"');
    expect($output)->toContain('id="timelineStream"');
    expect($output)->toContain('pollSection');
    expect($output)->toContain('offlineIndicator');
    expect($output)->toContain('/micropub');
    expect($output)->toContain('/microsub');

    // Test /reader alias
    $_SERVER['REQUEST_URI'] = '/reader';
    ob_start();
    $router->handleRequest();
    $readerOutput = ob_get_clean();

    expect($readerOutput)->toContain('Unified Client');
    expect($readerOutput)->toContain('id="composerModal"');
});

it('injects authentication token into client configuration when provided', function () {
    $router = new WebRouter($this->site);

    $_SERVER['REQUEST_URI'] = '/client?token=secret-auth-token-123';
    $_GET['token'] = 'secret-auth-token-123';

    ob_start();
    $router->handleRequest();
    $output = ob_get_clean();

    expect($output)->toContain("token: 'secret-auth-token-123'");
});
