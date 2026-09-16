<?php

declare(strict_types=1);

use Indieinabox\BackgroundWorker\BackgroundWorker;
use Indieinabox\BackgroundWorker\Contracts\BackgroundWorkerInterface;
use Indieinabox\Core\Database;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Services\Contracts\BackupServiceInterface;
use Indieinabox\Services\Contracts\UpdateServiceInterface;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;

$tempDir = __DIR__ . '/tmp_bgworker_unit';

beforeEach(function () use ($tempDir) {
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    Database::disconnect();
    $dbPath = $tempDir . '/test.sqlite';
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }
    Database::$dataDir = $tempDir;
    Database::connect($dbPath);
    $db = Database::getDb();
    $schema = file_get_contents(__DIR__ . '/../../../database.sql');
    $db->exec($schema);

    $paths = new Paths(
        $tempDir,
        $tempDir . '/public_html',
        $tempDir . '/public_gemini',
        $tempDir . '/public_gopher',
        $tempDir . '/public_media',
        $tempDir . '/content',
        $tempDir . '/resources'
    );
    $this->site = new Site(null, $paths);
    $GLOBALS['site'] = $this->site;
});

afterEach(function () use ($tempDir) {
    Database::disconnect();
    Database::$dataDir = '';

    if (is_dir($tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($tempDir);
    }
});

describe('BackgroundWorker', function () {
    it('implements BackgroundWorkerInterface', function () {
        $worker = new BackgroundWorker($this->site);
        expect($worker)->toBeInstanceOf(BackgroundWorkerInterface::class);
    });

    it('delegates backups to BackupServiceInterface and updates last_backup_date in settings', function () {
        $backupRan = false;
        $savedKey = null;
        $savedValue = null;

        $fakeSettings = new class($savedKey, $savedValue) implements SettingsRepositoryInterface {
            public mixed $savedKey;
            public mixed $savedValue;
            public function __construct(&$key, &$val)
            {
                $this->savedKey = &$key;
                $this->savedValue = &$val;
            }
            public function get(string $key, mixed $default = null): mixed { return null; }
            public function set(string $key, mixed $value): bool { $this->savedKey = $key; $this->savedValue = $value; return true; }
            public function all(): array { return ['backup_cron_enabled' => true, 'last_backup_date' => '2000-01-01']; }
            public function getTranslations(): array { return []; }
            public function saveTranslations(array $translations): bool { return true; }
            public function getUrlTranslations(): array { return []; }
            public function saveUrlTranslations(array $translations): bool { return true; }
            public function getKinds(): array { return []; }
            public function saveKinds(array $kinds): bool { return true; }
        };

        $fakeBackup = new class($backupRan) implements BackupServiceInterface {
            public bool $backupRan;
            public function __construct(&$flag) { $this->backupRan = &$flag; }
            public function run(bool $skipContent = false, bool $skipMedia = false): void { $this->backupRan = true; }
            public function rotateBackups(string $destDir, int $limit = 5): void {}
        };

        $worker = new BackgroundWorker(
            $this->site,
            $fakeSettings,
            null,
            null,
            $fakeBackup
        );

        $worker->processBackups();

        expect($backupRan)->toBeTrue();
        expect($savedKey)->toBe('last_backup_date');
        expect($savedValue)->toBe(date('Y-m-d'));
    });

    it('skips backups when backup_cron_enabled is falsy or already performed today', function () {
        $backupRan = false;

        $fakeSettings = new class implements SettingsRepositoryInterface {
            public function get(string $key, mixed $default = null): mixed { return null; }
            public function set(string $key, mixed $value): bool { return true; }
            public function all(): array { return ['backup_cron_enabled' => false]; }
            public function getTranslations(): array { return []; }
            public function saveTranslations(array $translations): bool { return true; }
            public function getUrlTranslations(): array { return []; }
            public function saveUrlTranslations(array $translations): bool { return true; }
            public function getKinds(): array { return []; }
            public function saveKinds(array $kinds): bool { return true; }
        };

        $fakeBackup = new class($backupRan) implements BackupServiceInterface {
            public bool $backupRan;
            public function __construct(&$flag) { $this->backupRan = &$flag; }
            public function run(bool $skipContent = false, bool $skipMedia = false): void { $this->backupRan = true; }
            public function rotateBackups(string $destDir, int $limit = 5): void {}
        };

        $worker = new BackgroundWorker(
            $this->site,
            $fakeSettings,
            null,
            null,
            $fakeBackup
        );

        $worker->processBackups();
        expect($backupRan)->toBeFalse();

        // Already run today
        $fakeSettingsToday = new class implements SettingsRepositoryInterface {
            public function get(string $key, mixed $default = null): mixed { return null; }
            public function set(string $key, mixed $value): bool { return true; }
            public function all(): array { return ['backup_cron_enabled' => true, 'last_backup_date' => date('Y-m-d')]; }
            public function getTranslations(): array { return []; }
            public function saveTranslations(array $translations): bool { return true; }
            public function getUrlTranslations(): array { return []; }
            public function saveUrlTranslations(array $translations): bool { return true; }
            public function getKinds(): array { return []; }
            public function saveKinds(array $kinds): bool { return true; }
        };

        $workerToday = new BackgroundWorker(
            $this->site,
            $fakeSettingsToday,
            null,
            null,
            $fakeBackup
        );

        $workerToday->processBackups();
        expect($backupRan)->toBeFalse();
    });

    it('delegates update check to UpdateServiceInterface', function () {
        $fakeUpdater = new class implements UpdateServiceInterface {
            public function getVersionsDir(): string { return ''; }
            public function checkAvailableVersions(): array { return []; }
            public function getAvailableUpdates(): array { return []; }
            public function getLatestRelease(bool $includePrerelease = false): ?array { return null; }
            public function downloadAndInstall(string $downloadUrl): bool { return true; }
            public function backupCurrentVersion(): string|false { return false; }
            public function getLocalBackups(): array { return []; }
            public function rollback(?string $backupFilename = null): bool { return true; }
            public function processScheduledUpdate(int $intervalSeconds = 21600): array {
                return ['checked' => true, 'upgraded' => false, 'message' => 'No updates needed.'];
            }
            public function cleanupOldBackups(): int { return 0; }
            public function getCurrentExecutablePath(): string { return ''; }
        };

        $worker = new BackgroundWorker(
            $this->site,
            null,
            null,
            $fakeUpdater,
            null
        );

        ob_start();
        $worker->processUpdates();
        $output = ob_get_clean();

        expect($output)->toContain('Checking for application updates...');
        expect($output)->toContain('No updates needed.');
    });
});
