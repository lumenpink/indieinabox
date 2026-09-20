<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Indieinabox\Console\Commands\SetupCommand;
use Indieinabox\Core\Database;
use Indieinabox\Site\Site;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_setup_cmd_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);

    $this->site = new Site();
    $this->site->paths->baseDir = $this->tempDir;
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('SetupCommand performs full headless setup with CLI options', function () {
    $cmd = new SetupCommand($this->site);

    $dbPath = $this->tempDir . '/my_data/custom.sqlite';
    $contentDir = $this->tempDir . '/my_content';

    $argv = [
        'indieinabox.php',
        'setup',
        '--name', 'Pink Cyber Blog',
        '--fqdn', 'https://pink.example',
        '--password', 'superSecret456!',
        '--author', 'Lumen Pink',
        '--db', $dbPath,
        '--lang', 'pt-br,en',
        '--content', $contentDir,
        '--non-interactive',
    ];

    ob_start();
    $code = $cmd->execute($argv);
    $out = ob_get_clean();

    expect($code)->toBe(0);
    expect($out)->toContain('Pink Cyber Blog')
        ->toContain('https://pink.example')
        ->toContain('pt-br, en')
        ->toContain('Indieinabox setup completed successfully');

    // 1. Verify .config.php was written correctly
    $configFile = $this->tempDir . '/.config.php';
    expect(file_exists($configFile))->toBeTrue();
    $config = require $configFile;
    expect($config['db_path'])->toBe($dbPath)
        ->and($config['data_dir'])->toBe(dirname($dbPath));

    // 2. Verify database settings were saved
    expect(Database::getSetting('sitename'))->toBe('Pink Cyber Blog')
        ->and(Database::getSetting('fqdn'))->toBe('https://pink.example')
        ->and(Database::getSetting('author'))->toBe('Lumen Pink')
        ->and(Database::getSetting('contentdir'))->toBe($contentDir)
        ->and(Database::getSetting('lang'))->toBe(['pt-br', 'en'])
        ->and(Database::getSetting('defaultlang'))->toBe('pt-br');

    // 3. Verify passwords (both indieauth and admin password)
    $indieAuthHash = (string) Database::getSetting('indieauth_password');
    $adminHash = (string) Database::getSetting('admin_password');
    expect(password_verify('superSecret456!', $indieAuthHash))->toBeTrue()
        ->and(password_verify('superSecret456!', $adminHash))->toBeTrue();

    // 4. Verify initial content seeded
    expect(is_dir($contentDir . '/articles'))->toBeTrue()
        ->and(is_dir($contentDir . '/notes'))->toBeTrue();
    expect(file_exists($contentDir . '/articles/welcome-to-indieinabox.md'))->toBeTrue()
        ->and(file_exists($contentDir . '/notes/first-note.md'))->toBeTrue();
});

test('SetupCommand auto-generates password when omitted in non-interactive mode', function () {
    $cmd = new SetupCommand($this->site);

    $argv = [
        'indieinabox.php',
        'setup',
        '--name', 'Automated Node',
        '-y',
    ];

    ob_start();
    $code = $cmd->execute($argv);
    $out = ob_get_clean();

    expect($code)->toBe(0);
    expect($out)->toContain('Automated Node')
        ->toContain('Auto-generated');

    expect(Database::getSetting('sitename'))->toBe('Automated Node');
    $hash = (string) Database::getSetting('indieauth_password');
    expect($hash)->not->toBeEmpty();
});
