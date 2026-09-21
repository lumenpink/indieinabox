<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Core\Database;
use Indieinabox\Services\InstallService;
use Indieinabox\Site\Site;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_install_service_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);

    $this->site = new Site();
    $this->site->paths->baseDir = $this->tempDir;
    $this->service = new InstallService($this->site);
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    FileUtils::recursiveRmdir($this->tempDir);
});

test('InstallService executes atomic 4-step installation workflow', function () {
    $dbPath = $this->tempDir . '/data/test.sqlite';
    $contentDir = $this->tempDir . '/content';

    $result = $this->service->install([
        'base_dir' => $this->tempDir,
        'db_path' => $dbPath,
        'sitename' => 'Atomic Site',
        'fqdn' => 'https://atomic.example',
        'password' => 'secretPass123',
        'contentdir' => $contentDir,
        'build' => true,
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['sitename'])->toBe('Atomic Site')
        ->and($result['fqdn'])->toBe('https://atomic.example')
        ->and($result['password'])->toBe('secretPass123')
        ->and($result['generated_password'])->toBeFalse()
        ->and($result['built'])->toBeTrue();

    // 1. Check .config.php
    $configFile = $this->tempDir . '/.config.php';
    expect(file_exists($configFile))->toBeTrue();
    $config = require $configFile;
    expect($config['db_path'])->toBe($dbPath);

    // 2. Check Database connected & schema migrated
    expect(Database::isConnected())->toBeTrue();
    expect(Database::getSetting('sitename'))->toBe('Atomic Site');

    // 3. Check seeded content
    expect(file_exists($contentDir . '/articles/welcome-to-indieinabox.md'))->toBeTrue()
        ->and(file_exists($contentDir . '/notes/first-note.md'))->toBeTrue();

    // 4. Check static site was built
    expect(is_dir($this->tempDir . '/public_html'))->toBeTrue();
    expect(file_exists($this->tempDir . '/public_html/index.html'))->toBeTrue();
});

test('InstallService auto-generates password if omitted', function () {
    $result = $this->service->install([
        'base_dir' => $this->tempDir,
        'sitename' => 'Auto Pass Site',
        'build' => false,
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['generated_password'])->toBeTrue()
        ->and(strlen((string) $result['password']))->toBe(16);

    $savedHash = (string) Database::getSetting('indieauth_password');
    expect(password_verify((string) $result['password'], $savedHash))->toBeTrue();
});
