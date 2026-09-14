<?php

declare(strict_types=1);

use Indieinabox\Console\Commands\BackupCommand;
use Indieinabox\Console\Commands\BuildCommand;
use Indieinabox\Console\Commands\ConfigCommand;
use Indieinabox\Console\Commands\CronCommand;
use Indieinabox\Console\Commands\FetchCommand;
use Indieinabox\Console\Commands\LinkCheckCommand;
use Indieinabox\Console\Commands\PostCommand;
use Indieinabox\Console\Commands\ProfileCommand;
use Indieinabox\Console\Commands\SetupCommand;
use Indieinabox\Console\Commands\TestWebmentionCommand;
use Indieinabox\Console\Commands\UpdateCommand;
use Indieinabox\Console\Commands\VersionCommand;
use Indieinabox\Database;
use Indieinabox\Site;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    Database::disconnect();
    $this->site = new Site();

    $this->tempDir = sys_get_temp_dir() . '/iiab_console_tests_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;

    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
});

afterEach(function () {
    /** @var \Tests\TestCase $this */
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('ProfileCommand edits identity and saves to database', function () {
    /** @var \Tests\TestCase $this */
    $cmd = new ProfileCommand($this->site);

    ob_start();
    $code = $cmd->execute(['indieinabox.php', 'profile', 'edit', '--username', 'test_user', '--name', 'Test Name', '--bio', 'Test bio']);
    $output = ob_get_clean();

    expect($code)->toBe(0);
    expect($output)->toContain('Username updated to: test_user')
                   ->toContain('Name updated to: Test Name')
                   ->toContain('Bio updated.');

    expect(Database::getSetting('activitypub_handle'))->toBe('test_user');
    expect(Database::getSetting('sitename'))->toBe('Test Name');
    expect(Database::getSetting('author'))->toBe('Test Name');
    expect(Database::getSetting('activitypub_bio'))->toBe('Test bio');
});

test('ProfileCommand media resizes and copies avatar and banner', function () {
    /** @var \Tests\TestCase $this */
    $cmd = new ProfileCommand($this->site);
    $avatarSrc = $this->tempDir . '/test_avatar.png';
    $bgSrc = $this->tempDir . '/test_bg.png';

    $img = imagecreatetruecolor(800, 800);
    imagepng($img, $avatarSrc);

    $img = imagecreatetruecolor(3000, 1000);
    imagepng($img, $bgSrc);

    ob_start();
    $code = $cmd->execute(['indieinabox.php', 'profile', 'media', '--avatar', $avatarSrc, '--background', $bgSrc]);
    $output = ob_get_clean();

    expect($code)->toBe(0);
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

test('PostCommand requires text option', function () {
    /** @var \Tests\TestCase $this */
    $cmd = new PostCommand($this->site);

    ob_start();
    $code = $cmd->execute(['indieinabox.php', 'post', 'create']);
    $output = ob_get_clean();

    expect($code)->toBe(1);
    expect($output)->toContain('Error: --text is required.');
});

test('ConfigCommand gets and sets configuration values', function () {
    /** @var \Tests\TestCase $this */
    $cmd = new ConfigCommand($this->site);

    ob_start();
    $codeSet = $cmd->execute(['indieinabox.php', 'config', 'set', '--key', 'test_key', '--value', 'sample_value']);
    $setOut = ob_get_clean();

    expect($codeSet)->toBe(0);
    expect($setOut)->toContain("Config 'test_key' updated.");
    expect(Database::getSetting('test_key'))->toBe('sample_value');

    ob_start();
    $codeGet = $cmd->execute(['indieinabox.php', 'config', 'get', '--key', 'test_key']);
    $getOut = ob_get_clean();

    expect($codeGet)->toBe(0);
    expect($getOut)->toContain("Config 'test_key': sample_value");
});

test('SetupCommand saves configuration values with valid inputs', function () {
    /** @var \Tests\TestCase $this */
    $cmd = new SetupCommand($this->site);

    ob_start();
    $code = $cmd->execute(['indieinabox.php', 'setup', '--password', 'secret123', '--name', 'My Site', '--fqdn', 'https://example.com']);
    $out = ob_get_clean();

    expect($code)->toBe(0);
    expect($out)->toContain('Setup complete.');
    expect(Database::getSetting('sitename'))->toBe('My Site');
    expect(Database::getSetting('fqdn'))->toBe('https://example.com');
    expect(password_verify('secret123', (string) Database::getSetting('admin_password')))->toBeTrue();
});

test('VersionCommand outputs version information', function () {
    /** @var \Tests\TestCase $this */
    $cmd = new VersionCommand($this->site);

    ob_start();
    $code = $cmd->execute(['indieinabox.php', 'version']);
    $output = ob_get_clean();

    expect($code)->toBe(0);
    expect($output)->toContain('Indieinabox version: ')
        ->toContain('Runtime Mode: ')
        ->toContain('PHP Version: ');
});

test('UpdateCommand backups outputs backup list', function () {
    /** @var \Tests\TestCase $this */
    $cmd = new UpdateCommand($this->site);
    $versionsDir = $this->tempDir . '/versions';
    \Indieinabox\Updater::$customVersionsDir = $versionsDir;

    ob_start();
    $code = $cmd->execute(['indieinabox.php', 'update', '--backups']);
    $output = ob_get_clean();

    expect($code)->toBe(0);
    expect($output)->toContain('Local version backups (retaining up to 2):')
        ->toContain('No backups found.');

    \Indieinabox\Updater::$customVersionsDir = null;
});

test('TestWebmentionCommand validates h-card markup', function () {
    /** @var \Tests\TestCase $this */
    $cmd = new TestWebmentionCommand($this->site);
    $htmlFile = $this->tempDir . '/test_hcard.html';
    file_put_contents($htmlFile, '<div class="h-card"><a class="p-name u-url" href="https://user.example">User</a></div>');

    ob_start();
    $code = $cmd->execute(['indieinabox.php', 'test-webmention', '--validate-hcard', $htmlFile]);
    $output = ob_get_clean();

    expect($code)->toBe(0);
    expect($output)->toContain("Validating IndieWebify.me Level 1 (h-card)")
        ->toContain("[OK] Found 'h-card' microformat.")
        ->toContain("[OK] Name (p-name): User")
        ->toContain("[OK] URL (u-url): https://user.example")
        ->toContain("Result: PASS");
});
