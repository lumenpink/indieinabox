<?php

declare(strict_types=1);

use Indieinabox\Updater;
use Indieinabox\Database;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->tempDir = sys_get_temp_dir() . '/updater_test_' . uniqid('', true);
    mkdir($this->tempDir, 0755, true);

    Database::disconnect();
    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");

    $this->versionsDir = $this->tempDir . '/versions';
    $this->mockExecutable = $this->tempDir . '/indieinabox.php';
    file_put_contents($this->mockExecutable, '<?php echo "original executable";');

    Updater::$customExecutablePath = $this->mockExecutable;
    Updater::$customVersionsDir = $this->versionsDir;
});

afterEach(function () {
    /** @var \Tests\TestCase $this */
    Updater::$customExecutablePath = null;
    Updater::$customVersionsDir = null;
    Database::disconnect();

    if (is_dir($this->tempDir)) {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

test('Updater enforces MAX_BACKUPS = 2', function () {
    expect(Updater::MAX_BACKUPS)->toBe(2);
});

test('Updater::getVersionsDir creates and returns versions directory', function () {
    /** @var \Tests\TestCase $this */
    $dir = Updater::getVersionsDir();
    expect(is_dir($dir))->toBeTrue();
    expect($dir)->toBe($this->versionsDir);
});

test('Updater::backupCurrentVersion creates backup file and enforces 2 backups limit', function () {
    /** @var \Tests\TestCase $this */
    // First backup
    $backup1 = Updater::backupCurrentVersion();
    expect($backup1)->not->toBeFalse();
    expect(file_exists((string) $backup1))->toBeTrue();
    expect(basename((string) $backup1))->toMatch('/^indieinabox_backup_v.+\.php$/');

    sleep(1);

    // Second backup
    $backup2 = Updater::backupCurrentVersion();
    expect($backup2)->not->toBeFalse();
    expect(file_exists((string) $backup2))->toBeTrue();

    $backups = Updater::getLocalBackups();
    expect(count($backups))->toBe(2);

    sleep(1);

    // Third backup - should rotate and prune oldest
    $backup3 = Updater::backupCurrentVersion();
    expect($backup3)->not->toBeFalse();

    $backupsAfter = Updater::getLocalBackups();
    expect(count($backupsAfter))->toBe(2);

    // Verify oldest was pruned
    expect(file_exists((string) $backup1))->toBeFalse();
    expect(file_exists((string) $backup2))->toBeTrue();
    expect(file_exists((string) $backup3))->toBeTrue();
});

test('Updater::getLocalBackups parses version and sorts descending by date', function () {
    /** @var \Tests\TestCase $this */
    $dir = $this->versionsDir;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $file1 = $dir . '/indieinabox_backup_v0.1.0_20260901_100000.php';
    $file2 = $dir . '/indieinabox_backup_v0.2.0_20260902_100000.php';

    file_put_contents($file1, '<?php // backup 1');
    touch($file1, 1700000000);

    file_put_contents($file2, '<?php // backup 2');
    touch($file2, 1700001000);

    $backups = Updater::getLocalBackups();
    expect(count($backups))->toBe(2);
    expect($backups[0]['filename'])->toBe(basename($file2));
    expect($backups[0]['version'])->toBe('0.2.0');
    expect($backups[1]['filename'])->toBe(basename($file1));
    expect($backups[1]['version'])->toBe('0.1.0');
});

test('Updater::rollback restores content from backup', function () {
    /** @var \Tests\TestCase $this */
    // Create initial executable content
    file_put_contents($this->mockExecutable, '<?php echo "version 1.0";');
    $backup = Updater::backupCurrentVersion();
    expect($backup)->not->toBeFalse();

    // Overwrite executable with new version
    file_put_contents($this->mockExecutable, '<?php echo "version 2.0";');
    expect(file_get_contents($this->mockExecutable))->toBe('<?php echo "version 2.0";');

    // Rollback to previous backup
    $success = Updater::rollback(basename((string) $backup));
    expect($success)->toBeTrue();
    expect(file_get_contents($this->mockExecutable))->toBe('<?php echo "version 1.0";');
});

test('Updater::processScheduledUpdate skips check if recent', function () {
    /** @var \Tests\TestCase $this */
    Database::saveSetting('last_update_check', time());

    $result = Updater::processScheduledUpdate();
    expect($result['checked'])->toBeFalse();
    expect($result['upgraded'])->toBeFalse();
    expect($result['message'])->toContain('Skipping update check');
});
