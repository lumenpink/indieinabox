<?php

declare(strict_types=1);

use Indieinabox\Core\Container;
use Indieinabox\Database;
use Indieinabox\Repositories\Contracts\InteractionRepositoryInterface;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Repositories\FileInteractionRepository;
use Indieinabox\Repositories\SqliteSettingsRepository;
use Indieinabox\Services\ConfigurationService;
use Indieinabox\Services\ModerationService;

$integTempDir = __DIR__ . '/tmp_integ_repos';

beforeEach(function () use ($integTempDir) {
    if (!is_dir($integTempDir)) {
        mkdir($integTempDir, 0777, true);
    }

    Database::disconnect();
    $testDbPath = $integTempDir . '/test.sqlite';
    if (file_exists($testDbPath)) {
        unlink($testDbPath);
    }
    Database::$dataDir = $integTempDir . '/data';
    Database::connect($testDbPath);
    $db = Database::getDb();

    $schema = file_get_contents(__DIR__ . '/../../database.sql');
    $db->exec($schema);

    Container::getInstance()->flush();
});

afterEach(function () use ($integTempDir) {
    Database::disconnect();
    Database::$dataDir = '';
    Container::getInstance()->flush();

    if (is_dir($integTempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($integTempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($integTempDir);
    }
});

describe('Repositories Architecture Integration', function () {
    it('autowires SettingsRepositoryInterface and InteractionRepositoryInterface as Singletons', function () {
        $container = Container::getInstance();

        $settingsRepo1 = $container->make(SettingsRepositoryInterface::class);
        $settingsRepo2 = $container->make(SettingsRepositoryInterface::class);
        expect($settingsRepo1)->toBeInstanceOf(SqliteSettingsRepository::class);
        expect($settingsRepo1)->toBe($settingsRepo2);

        $interactionRepo1 = $container->make(InteractionRepositoryInterface::class);
        $interactionRepo2 = $container->make(InteractionRepositoryInterface::class);
        expect($interactionRepo1)->toBeInstanceOf(FileInteractionRepository::class);
        expect($interactionRepo1)->toBe($interactionRepo2);
    });

    it('injects repository contracts cleanly into domain services', function () {
        $container = Container::getInstance();

        $configService = $container->make(ConfigurationService::class);
        expect($configService)->toBeInstanceOf(ConfigurationService::class);
        expect($configService->getSettingsRepository())->toBeInstanceOf(SettingsRepositoryInterface::class);

        $moderationService = $container->make(ModerationService::class);
        expect($moderationService)->toBeInstanceOf(ModerationService::class);
        expect($moderationService->getRepository())->toBeInstanceOf(InteractionRepositoryInterface::class);
    });

    it('routes Database static facade calls through bound SettingsRepositoryInterface instance', function () {
        $container = Container::getInstance();

        $mockRepo = new class implements SettingsRepositoryInterface {
            private array $store = ['injected_flag' => 'active_value'];
            public function get(string $key, mixed $default = null): mixed { return $this->store[$key] ?? $default; }
            public function set(string $key, mixed $value): bool { $this->store[$key] = $value; return true; }
            public function all(): array { return $this->store; }
            public function getTranslations(): array { return []; }
            public function getUrlTranslations(): array { return []; }
            public function getKinds(): array { return ['custom_kind' => []]; }
            public function saveKinds(array $kinds): bool { return true; }
            public function saveTranslations(array $translations): bool { return true; }
            public function saveUrlTranslations(array $urlTranslations): bool { return true; }
        };

        $container->instance(SettingsRepositoryInterface::class, $mockRepo);
        Database::setSettingsRepository($mockRepo);

        expect(Database::getSetting('injected_flag'))->toBe('active_value');
        expect(Database::getKinds())->toHaveKey('custom_kind');

        Database::saveSetting('new_key', 42);
        expect($mockRepo->get('new_key'))->toBe(42);
    });
});
