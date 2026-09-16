<?php

declare(strict_types=1);

use Indieinabox\Core\Bootstrap;
use Indieinabox\Core\Database;

it('passes checkVersion when PHP version is compatible (>= 8.2)', function () {
    $result = Bootstrap::checkVersion(80200, '8.2.0', 'cli');
    expect($result)->toBeTrue();

    $resultCurrent = Bootstrap::checkVersion();
    expect($resultCurrent)->toBeTrue();
});

it('handles checkVersion failure in CLI mode', function () {
    $exitedWith = null;
    $capturedMessage = null;
    $exitHandler = function (int $code) use (&$exitedWith): void {
        $exitedWith = $code;
    };
    $outputHandler = function (string $msg) use (&$capturedMessage): void {
        $capturedMessage = $msg;
    };

    $result = Bootstrap::checkVersion(
        80100,
        '8.1.0',
        'cli',
        $exitHandler,
        null,
        null,
        $outputHandler
    );

    expect($exitedWith)->toBe(1);
    expect($capturedMessage)->toContain('requires PHP version 8.2.0');
    expect($result)->toBeFalse();
});

it('handles checkVersion failure in Web SAPI mode', function () {
    $headers = [];
    $output = '';
    $exitedWith = null;

    $headerHandler = function (string $h) use (&$headers): void {
        $headers[] = $h;
    };
    $echoHandler = function (string $msg) use (&$output): void {
        $output .= $msg;
    };
    $exitHandler = function (int $code) use (&$exitedWith): void {
        $exitedWith = $code;
    };

    $result = Bootstrap::checkVersion(
        80100,
        '8.1.0',
        'fpm-fcgi',
        $exitHandler,
        $headerHandler,
        $echoHandler
    );

    expect($exitedWith)->toBe(1);
    expect($result)->toBeFalse();
    expect($headers)->toContain('HTTP/1.1 500 Internal Server Error');
    expect($headers)->toContain('Content-Type: text/html; charset=utf-8');
    expect($output)->toContain('PHP Version Error');
    expect($output)->toContain('8.1.0');
});

it('registers and exercises fallback autoloader', function () {
    $baseDir = dirname(__DIR__, 2);
    $autoloader = Bootstrap::registerAutoloader($baseDir);

    // 1. Existing class under Indieinabox\ namespace
    $autoloader('Indieinabox\\Page');
    // Call again now that class is loaded to test early return
    $autoloader('Indieinabox\\Page');
    expect(class_exists(\Indieinabox\Page\Page::class, false))->toBeTrue();

    // 2. Non-existent class under Indieinabox\ namespace
    $autoloader('Indieinabox\\NonExistentClassXYZ');
    expect(class_exists('Indieinabox\\NonExistentClassXYZ', false))->toBeFalse();

    // 3. Class under another namespace
    $autoloader('SomeOther\\Namespace\\Foo');
    expect(class_exists('SomeOther\\Namespace\\Foo', false))->toBeFalse();
});

it('loads helper functions from app/functions', function () {
    $baseDir = dirname(__DIR__, 2);
    $loaded = Bootstrap::loadFunctions($baseDir);

    expect($loaded)->toBeArray();
    expect(count($loaded))->toBeGreaterThan(0);
});

it('handles loadConfig when .config.php is missing in CLI', function () {
    $tempDir = sys_get_temp_dir() . '/indie_test_cfg_cli_' . uniqid();
    mkdir($tempDir, 0777, true);

    $exitedWith = null;
    $capturedMsg = null;
    $exitHandler = function (int $code) use (&$exitedWith): void {
        $exitedWith = $code;
    };
    $outputHandler = function (string $msg) use (&$capturedMsg): void {
        $capturedMsg = $msg;
    };

    $config = Bootstrap::loadConfig($tempDir, 'cli', $exitHandler, null, null, $outputHandler);

    expect($exitedWith)->toBe(1);
    expect($capturedMsg)->toContain('Database is not configured');
    expect($config)->toBeEmpty();

    rmdir($tempDir);
});

it('handles loadConfig when .config.php is missing in Web mode', function () {
    $tempDir = sys_get_temp_dir() . '/indie_test_cfg_web_' . uniqid();
    mkdir($tempDir, 0777, true);

    $exitedWith = null;
    $installedWith = null;

    $exitHandler = function (int $code) use (&$exitedWith): void {
        $exitedWith = $code;
    };
    $installHandler = function (string $installer) use (&$installedWith): void {
        $installedWith = $installer;
    };

    $config = Bootstrap::loadConfig($tempDir, 'fpm-fcgi', $exitHandler, $installHandler);

    expect($exitedWith)->toBe(0);
    expect($installedWith)->toBe($tempDir . '/install.php');
    expect($config)->toBeEmpty();

    rmdir($tempDir);
});

it('loads valid configuration with data_dir', function () {
    $tempDir = sys_get_temp_dir() . '/indie_test_cfg_valid_' . uniqid();
    mkdir($tempDir, 0777, true);

    file_put_contents(
        $tempDir . '/.config.php',
        "<?php return ['data_dir' => '{$tempDir}/data', 'db_path' => '{$tempDir}/data/test.sqlite'];"
    );

    $config = Bootstrap::loadConfig($tempDir, 'cli');

    expect($config['data_dir'])->toBe($tempDir . '/data');
    expect($config['db_path'])->toBe($tempDir . '/data/test.sqlite');

    unlink($tempDir . '/.config.php');
    rmdir($tempDir);
});

it('falls back to dirname(db_path) when data_dir is missing in config', function () {
    $tempDir = sys_get_temp_dir() . '/indie_test_cfg_legacy_' . uniqid();
    mkdir($tempDir, 0777, true);

    file_put_contents(
        $tempDir . '/.config.php',
        "<?php return ['db_path' => '{$tempDir}/legacy/db.sqlite'];"
    );

    $config = Bootstrap::loadConfig($tempDir, 'cli');

    expect($config['data_dir'])->toBe($tempDir . '/legacy');

    unlink($tempDir . '/.config.php');
    rmdir($tempDir);
});

it('triggers die handler when config has neither data_dir nor db_path', function () {
    $tempDir = sys_get_temp_dir() . '/indie_test_cfg_invalid_' . uniqid();
    mkdir($tempDir, 0777, true);

    file_put_contents($tempDir . '/.config.php', "<?php return ['some_key' => 'val'];");

    $diedWith = null;
    $dieHandler = function (string $msg) use (&$diedWith): void {
        $diedWith = $msg;
    };

    $config = Bootstrap::loadConfig($tempDir, 'cli', null, null, $dieHandler);

    expect($diedWith)->toContain("Missing 'data_dir'");
    expect($config)->toBeEmpty();

    unlink($tempDir . '/.config.php');
    rmdir($tempDir);
});

it('connects database successfully', function () {
    $ref = new \ReflectionClass(Database::class);
    $prop = $ref->getProperty('db');
    $originalDb = $prop->getValue();

    try {
        $tempDir = sys_get_temp_dir() . '/indie_test_db_' . uniqid();
        mkdir($tempDir, 0777, true);
        $dbFile = $tempDir . '/test.sqlite';
        touch($dbFile);

        // 1. With explicit db_path
        $prop->setValue(null, null);
        Bootstrap::connectDatabase(['data_dir' => $tempDir, 'db_path' => $dbFile]);
        expect(Database::$dataDir)->toBe($tempDir);

        // 2. Without db_path (defaults to data_dir/.indieinabox.sqlite)
        $prop->setValue(null, null);
        Bootstrap::connectDatabase(['data_dir' => $tempDir]);
        expect(Database::$dataDir)->toBe($tempDir);

        // Close connection before removing files
        $prop->setValue(null, null);

        foreach (glob($tempDir . '/*') as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        foreach (glob($tempDir . '/.*') as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        @rmdir($tempDir);
    } finally {
        $prop->setValue(null, $originalDb);
    }
});

it('handles database connection failure gracefully', function () {
    $ref = new \ReflectionClass(Database::class);
    $prop = $ref->getProperty('db');
    $originalDb = $prop->getValue();

    try {
        $prop->setValue(null, null);
        $diedWith = null;
        $dieHandler = function (string $msg) use (&$diedWith): void {
            $diedWith = $msg;
        };

        Bootstrap::connectDatabase(['data_dir' => '/non/existent/path/that/cannot/exist'], $dieHandler);

        expect($diedWith)->toBeString();
        expect($diedWith)->toContain('Database Connection Error');
    } finally {
        $prop->setValue(null, $originalDb);
    }
});

it('runs full Bootstrap pipeline', function () {
    $baseDir = dirname(__DIR__, 2);
    Bootstrap::run($baseDir);

    expect(Database::$dataDir)->not->toBeEmpty();
});

it('executes bootstrap/app.php file cleanly', function () {
    $bootstrapFile = dirname(__DIR__, 2) . '/bootstrap/app.php';
    expect(file_exists($bootstrapFile))->toBeTrue();

    if (getenv('TEST_COMPILED') === 'true' || getenv('TEST_COMPILED') === '1') {
        expect(defined('DS'))->toBeTrue();
        return;
    }

    require $bootstrapFile;
    expect(defined('DS'))->toBeTrue();
});
