<?php

declare(strict_types=1);

use Indieinabox\Console\ConsoleKernel;
use Indieinabox\Database;
use Indieinabox\Site;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_functional_console_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/content');
    mkdir($this->tempDir . '/public');
    mkdir($this->tempDir . '/resources');
    mkdir($this->tempDir . '/resources/views');

    $this->site = new Site();
    $this->site->paths->contentDir = $this->tempDir . '/content';
    $this->site->paths->outputDirHtml = $this->tempDir . '/public';
    $this->site->paths->themeDir = $this->tempDir . '/resources';

    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");

    $this->kernel = new ConsoleKernel($this->site);
});

afterEach(function () {
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('functional CLI workflow handles config, profile, and help executions', function () {
    // 1. Help
    ob_start();
    $helpCode = $this->kernel->handle(['indieinabox.php', '--help']);
    $helpOut = ob_get_clean();
    expect($helpCode)->toBe(0);
    expect($helpOut)->toContain('Indieinabox CLI Console');

    // 2. Config set & get
    ob_start();
    $setCode = $this->kernel->handle(['indieinabox.php', 'config', 'set', '--key', 'workflow_test', '--value', 'ok']);
    ob_get_clean();
    expect($setCode)->toBe(0);

    ob_start();
    $getCode = $this->kernel->handle(['indieinabox.php', 'config', 'get', '--key', 'workflow_test']);
    $getOut = ob_get_clean();
    expect($getCode)->toBe(0);
    expect($getOut)->toContain("Config 'workflow_test': ok");

    // 3. Version
    ob_start();
    $verCode = $this->kernel->handle(['indieinabox.php', 'version']);
    $verOut = ob_get_clean();
    expect($verCode)->toBe(0);
    expect($verOut)->toContain('Indieinabox version: ');
});
