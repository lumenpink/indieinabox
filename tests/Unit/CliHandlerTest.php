<?php

use Indieinabox\CliHandler;
use Indieinabox\Site;
use Indieinabox\Database;

/**
 * @property Site $site
 * @property CliHandler $handler
 * @property string $tempDir
 */
beforeEach(function () {
    Database::disconnect();
    $this->site = new Site();
    $this->handler = new CliHandler($this->site);
    
    $this->tempDir = sys_get_temp_dir() . '/iiab_unit_tests_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;
    
    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
});

afterEach(function () {
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('handleProfile edit saves handle and name', function () {
    ob_start();
    $this->handler->handleProfile(['indieinabox.php', 'profile', 'edit', '--username', 'test_user', '--name', 'Test Name', '--bio', 'Test bio']);
    $output = ob_get_clean();

    expect($output)->toContain('Username updated to: test_user')
                   ->toContain('Name updated to: Test Name')
                   ->toContain('Bio updated.');

    expect(Database::getSetting('activitypub_handle'))->toBe('test_user');
    expect(Database::getSetting('sitename'))->toBe('Test Name');
    expect(Database::getSetting('author'))->toBe('Test Name');
    expect(Database::getSetting('activitypub_bio'))->toBe('Test bio');
});

test('handleProfile media resizes and copies avatar and background', function () {
    $avatarSrc = $this->tempDir . '/test_avatar.png';
    $bgSrc = $this->tempDir . '/test_bg.png';
    
    $img = imagecreatetruecolor(800, 800);
    imagepng($img, $avatarSrc);
    
    $img = imagecreatetruecolor(3000, 1000);
    imagepng($img, $bgSrc);

    ob_start();
    $this->handler->handleProfile(['indieinabox.php', 'profile', 'media', '--avatar', $avatarSrc, '--background', $bgSrc]);
    $output = ob_get_clean();

    expect($output)->toContain('Avatar updated.')
                   ->toContain('Background updated.');

    $publicMediaDir = $this->tempDir . '/../public_media';
    expect(file_exists($publicMediaDir . '/avatar.png'))->toBeTrue();
    expect(file_exists($publicMediaDir . '/background.png'))->toBeTrue();
    
    $avatarInfo = getimagesize($publicMediaDir . '/avatar.png');
    expect($avatarInfo[0])->toBe(400);

    $bgInfo = getimagesize($publicMediaDir . '/background.png');
    expect($bgInfo[0])->toBe(1500);
});

test('handlePost create requires text', function () {
    ob_start();
    $this->handler->handlePost(['indieinabox.php', 'post', 'create']);
    $output = ob_get_clean();

    expect($output)->toContain('Error: --text is required.');
});
