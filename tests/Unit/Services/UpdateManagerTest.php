<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Services\Contracts\UpdateServiceInterface;
use Indieinabox\Services\UpdateManager;
use Indieinabox\Services\UpdateService;

$tempDir = sys_get_temp_dir() . '/update_mgr_unit_' . uniqid('', true);

beforeEach(function () use ($tempDir) {
    mkdir($tempDir, 0755, true);

    Database::disconnect();
    Database::$dataDir = $tempDir;
    $dbPath = $tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");

    $this->versionsDir = $tempDir . '/versions';
    $this->mockExecutable = $tempDir . '/indieinabox.php';
    file_put_contents($this->mockExecutable, '<?php echo "test executable";');

    UpdateService::$customExecutablePath = $this->mockExecutable;
    UpdateService::$customVersionsDir = $this->versionsDir;
});

afterEach(function () use ($tempDir) {
    UpdateService::$customExecutablePath = null;
    UpdateService::$customVersionsDir = null;
    Database::disconnect();

    if (is_dir($tempDir)) {
        exec('rm -rf ' . escapeshellarg($tempDir));
    }
});

describe('UpdateManager', function () {
    it('implements UpdateServiceInterface', function () {
        $manager = new UpdateManager();
        expect($manager)->toBeInstanceOf(UpdateServiceInterface::class);
    });

    it('creates versions directory and handles backups', function () {
        $manager = new UpdateManager();
        $dir = $manager->getVersionsDir();
        expect(is_dir($dir))->toBeTrue();

        $backup1 = $manager->backupCurrentVersion();
        expect($backup1)->not->toBeFalse();
        expect(file_exists((string) $backup1))->toBeTrue();

        $backups = $manager->getLocalBackups();
        expect($backups)->toHaveCount(1);
    });

    it('respects injected SettingsRepositoryInterface in scheduled update checks', function () {
        $lastCheckedTime = time() - 30000; // > 6 hours ago
        $settingsStore = [
            'last_update_check' => $lastCheckedTime,
            'auto_upgrade_nightly' => false,
            'auto_upgrade_stable' => false,
        ];

        $mockRepo = new class($settingsStore) implements SettingsRepositoryInterface {
            public array $store;
            public function __construct(array &$store) { $this->store = &$store; }
            public function get(string $key, mixed $default = null): mixed { return $this->store[$key] ?? $default; }
            public function set(string $key, mixed $value): bool { $this->store[$key] = $value; return true; }
            public function all(): array { return $this->store; }
            public function getTranslations(): array { return []; }
            public function saveTranslations(array $translations): bool { return true; }
            public function getUrlTranslations(): array { return []; }
            public function saveUrlTranslations(array $translations): bool { return true; }
            public function getKinds(): array { return []; }
            public function saveKinds(array $kinds): bool { return true; }
        };

        $manager = new UpdateManager($mockRepo);

        // Skips when recent
        $recentResult = $manager->processScheduledUpdate(100000);
        expect($recentResult['checked'])->toBeFalse();
        expect($recentResult['message'])->toContain('Skipping update check');
    });
});
