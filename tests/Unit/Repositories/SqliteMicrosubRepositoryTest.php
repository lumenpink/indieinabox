<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Repositories\SqliteMicrosubRepository;

$tempDir = __DIR__ . '/tmp_microsub_repo_unit';

beforeEach(function () use ($tempDir) {
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    Database::disconnect();
    $dbPath = $tempDir . '/test.sqlite';
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }
    Database::$dataDir = $tempDir . '/data';
    Database::connect($dbPath);
    $db = Database::getDb();
    $schema = file_get_contents(__DIR__ . '/../../../database.sql');
    $db->exec($schema);
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

describe('SqliteMicrosubRepository', function () {
    it('manages channels lifecycle', function () {
        $repo = new SqliteMicrosubRepository();

        $channels = $repo->getChannels();
        // database.sql seeds default channels (e.g. notifications)
        $channelUids = array_column($channels, 'uid');
        expect($channelUids)->toContain('notifications');

        // Create new channel
        expect($repo->createChannel('tech-news', 'Tech News'))->toBeTrue();
        $updatedChannels = $repo->getChannels();
        $updatedUids = array_column($updatedChannels, 'uid');
        expect($updatedUids)->toContain('tech-news');

        // Delete channel
        expect($repo->deleteChannel('tech-news'))->toBeTrue();
        $finalChannels = $repo->getChannels();
        $finalUids = array_column($finalChannels, 'uid');
        expect($finalUids)->not->toContain('tech-news');
    });

    it('manages channel subscriptions lifecycle', function () {
        $repo = new SqliteMicrosubRepository();

        expect($repo->createChannel('reading', 'Reading'))->toBeTrue();

        expect($repo->getSubscriptions('reading'))->toBeEmpty();
        expect($repo->getSubscriptionType('reading', 'https://example.com/feed.xml'))->toBeNull();
        expect($repo->countSubscriptionsByUrl('https://example.com/feed.xml'))->toBe(0);

        // Add subscription
        expect($repo->addSubscription('reading', 'https://example.com/feed.xml', 'feed', 'Example Feed'))->toBeTrue();

        expect($repo->getSubscriptionType('reading', 'https://example.com/feed.xml'))->toBe('feed');
        expect($repo->countSubscriptionsByUrl('https://example.com/feed.xml'))->toBe(1);

        $subs = $repo->getSubscriptions('reading');
        expect($subs)->toHaveCount(1);
        expect($subs[0]['url'])->toBe('https://example.com/feed.xml');
        expect($subs[0]['name'])->toBe('Example Feed');

        // Remove subscription
        expect($repo->removeSubscription('reading', 'https://example.com/feed.xml'))->toBeTrue();
        expect($repo->getSubscriptions('reading'))->toBeEmpty();
        expect($repo->getSubscriptionType('reading', 'https://example.com/feed.xml'))->toBeNull();
        expect($repo->countSubscriptionsByUrl('https://example.com/feed.xml'))->toBe(0);
    });
});
