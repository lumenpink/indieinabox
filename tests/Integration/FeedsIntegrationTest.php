<?php

declare(strict_types=1);

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Http\Controllers\MicrosubController;
use Indieinabox\Services\FetchFeedsService;
use Indieinabox\Services\MicrosubService;
use Indieinabox\Site\Site;

$integTempDir = __DIR__ . '/tmp_integ_feeds';

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

describe('Feeds and Microsub Integration', function () {
    it('autowires FetchFeedsService and MicrosubService through DI Container', function () {
        $container = Container::getInstance();

        $fetchService = $container->make(FetchFeedsService::class);
        expect($fetchService)->toBeInstanceOf(FetchFeedsService::class);
        expect($fetchService->getParsers())->toHaveKeys(['twtxt', 'jsonfeed', 'rss', 'atom']);

        $microsubService = $container->make(MicrosubService::class);
        expect($microsubService)->toBeInstanceOf(MicrosubService::class);
        expect($microsubService->getChannels())->toBeArray();
    });

    it('autowires MicrosubController with service dependencies and Site metadata', function () {
        $container = Container::getInstance();
        $site = new Site();
        $container->instance(Site::class, $site);

        $controller = $container->make(MicrosubController::class);
        expect($controller)->toBeInstanceOf(MicrosubController::class);
        expect($controller->getSite())->toBe($site);
    });

    it('processes feed synchronization and timeline query end-to-end via service layer', function () {
        $container = Container::getInstance();
        $fetchService = $container->make(FetchFeedsService::class);
        $microsubService = $container->make(MicrosubService::class);

        $twtxtData = "# nick = IntegrationBot\n2026-09-14T19:00:00Z\tLive integration ping";
        $saved = $fetchService->fetchSubscription('inbox', 'https://bot.local/twtxt.txt', $twtxtData);
        expect($saved)->toBe(1);

        $timeline = $microsubService->getTimeline('inbox');
        expect($timeline['items'])->toHaveCount(1);
        expect($timeline['items'][0]['content']['text'])->toBe('Live integration ping');
        expect($timeline['items'][0]['author']['name'])->toBe('IntegrationBot');
    });
});
