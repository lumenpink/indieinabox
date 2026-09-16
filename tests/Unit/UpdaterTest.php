<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Services\UpdateService;

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

    UpdateService::$customExecutablePath = $this->mockExecutable;
    UpdateService::$customVersionsDir = $this->versionsDir;
});

afterEach(function () {
    /** @var \Tests\TestCase $this */
    UpdateService::$customExecutablePath = null;
    UpdateService::$customVersionsDir = null;
    Database::disconnect();

    if (is_dir($this->tempDir)) {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

test('Updater enforces MAX_BACKUPS = 2', function () {
    expect(UpdateService::MAX_BACKUPS)->toBe(2);
});

test('UpdateService::getVersionsDir creates and returns versions directory', function () {
    /** @var \Tests\TestCase $this */
    $dir = UpdateService::getVersionsDir();
    expect(is_dir($dir))->toBeTrue();
    expect($dir)->toBe($this->versionsDir);
});

test('UpdateService::backupCurrentVersion creates backup file and enforces 2 backups limit', function () {
    /** @var \Tests\TestCase $this */
    // First backup
    $backup1 = UpdateService::backupCurrentVersion();
    expect($backup1)->not->toBeFalse();
    expect(file_exists((string) $backup1))->toBeTrue();
    expect(basename((string) $backup1))->toMatch('/^indieinabox_backup_v.+\.php$/');

    sleep(1);

    // Second backup
    $backup2 = UpdateService::backupCurrentVersion();
    expect($backup2)->not->toBeFalse();
    expect(file_exists((string) $backup2))->toBeTrue();

    $backups = UpdateService::getLocalBackups();
    expect(count($backups))->toBe(2);

    sleep(1);

    // Third backup - should rotate and prune oldest
    $backup3 = UpdateService::backupCurrentVersion();
    expect($backup3)->not->toBeFalse();

    $backupsAfter = UpdateService::getLocalBackups();
    expect(count($backupsAfter))->toBe(2);

    // Verify oldest was pruned
    expect(file_exists((string) $backup1))->toBeFalse();
    expect(file_exists((string) $backup2))->toBeTrue();
    expect(file_exists((string) $backup3))->toBeTrue();
});

test('UpdateService::getLocalBackups parses version and sorts descending by date', function () {
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

    $backups = UpdateService::getLocalBackups();
    expect(count($backups))->toBe(2);
    expect($backups[0]['filename'])->toBe(basename($file2));
    expect($backups[0]['version'])->toBe('0.2.0');
    expect($backups[1]['filename'])->toBe(basename($file1));
    expect($backups[1]['version'])->toBe('0.1.0');
});

test('UpdateService::rollback restores content from backup', function () {
    /** @var \Tests\TestCase $this */
    // Create initial executable content
    file_put_contents($this->mockExecutable, '<?php echo "version 1.0";');
    $backup = UpdateService::backupCurrentVersion();
    expect($backup)->not->toBeFalse();

    // Overwrite executable with new version
    file_put_contents($this->mockExecutable, '<?php echo "version 2.0";');
    expect(file_get_contents($this->mockExecutable))->toBe('<?php echo "version 2.0";');

    // Rollback to previous backup
    $success = UpdateService::rollback(basename((string) $backup));
    expect($success)->toBeTrue();
    expect(file_get_contents($this->mockExecutable))->toBe('<?php echo "version 1.0";');
});

test('UpdateService::processScheduledUpdate skips check if recent', function () {
    /** @var \Tests\TestCase $this */
    Database::saveSetting('last_update_check', time());

    $result = UpdateService::processScheduledUpdate();
    expect($result['checked'])->toBeFalse();
    expect($result['upgraded'])->toBeFalse();
    expect($result['message'])->toContain('Skipping update check');
});
