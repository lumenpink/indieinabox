<?php

declare(strict_types=1);

use Indieinabox\Console\ConsoleKernel;
use Indieinabox\Core\Container;
use Indieinabox\Database;
use Indieinabox\Site;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_integration_container_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/content');
    mkdir($this->tempDir . '/public');

    $this->site = new Site();
    $this->site->paths->contentDir = $this->tempDir . '/content';
    $this->site->paths->outputDirHtml = $this->tempDir . '/public';

    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");

    Container::getInstance()->instance(Site::class, $this->site);
    unset($GLOBALS['site']);
});

afterEach(function () {
    Container::getInstance()->flush();
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('Container autowires ConsoleKernel and commands in integration environment without global site', function () {
    expect(isset($GLOBALS['site']))->toBeFalse();

    /** @var ConsoleKernel $kernel */
    $kernel = Container::getInstance()->make(ConsoleKernel::class);

    expect($kernel)->toBeInstanceOf(ConsoleKernel::class);

    ob_start();
    $exitCode = $kernel->handle(['indieinabox.php', 'config', 'set', '--key', 'di_test_key', '--value', 'di_test_val']);
    ob_get_clean();

    expect($exitCode)->toBe(0);
    expect(Database::getSetting('di_test_key'))->toBe('di_test_val');
});
