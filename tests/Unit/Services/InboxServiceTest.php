<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Core\Database;
use Indieinabox\Federation\FederationManager;
use Indieinabox\Services\FollowService;
use Indieinabox\Services\InboxService;
use Indieinabox\Services\OutboxService;
use Indieinabox\Site;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_inbox_srv_test_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;

    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS inbox_queue (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        payload_json TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS activitypub_followers (
        actor_url TEXT PRIMARY KEY,
        inbox_url TEXT NOT NULL,
        shared_inbox_url TEXT
    )");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS activitypub_outbox (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payload_json TEXT NOT NULL,
        target_inbox TEXT NOT NULL,
        status TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS activitypub_keys (key_id TEXT PRIMARY KEY, private_key TEXT NOT NULL, public_key TEXT NOT NULL, created_at INTEGER NOT NULL)");

    Database::saveSetting('fqdn', 'https://example.com');
    Database::saveSetting('activitypub_handle', 'alice');

    $this->site = new Site();
    $this->site->metadata->fqdn = 'https://example.com';
    $this->federationManager = new FederationManager($this->site);
    $this->followService = new FollowService(Database::getDb());
    $this->outboxService = new OutboxService($this->federationManager, $this->followService, Database::getDb());
    $this->inboxService = new InboxService(
        $this->site,
        $this->federationManager,
        $this->followService,
        $this->outboxService,
        Database::getDb()
    );
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    \Indieinabox\Core\Container::getInstance()->flush();
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('InboxService enqueues and drains pending items from inbox_queue', function () {
    $id = $this->inboxService->enqueue('activitypub', [
        'type' => 'Like',
        'actor' => 'https://remote.example/@bob',
        'object' => 'https://example.com/notes/1',
    ]);
    expect($id)->toBeGreaterThan(0);

    $processed = $this->inboxService->processPendingQueue(10);
    expect($processed)->toBe(1);

    // Queue should now be empty
    $processedAgain = $this->inboxService->processPendingQueue(10);
    expect($processedAgain)->toBe(0);
});

test('InboxService handles Follow activity and queues Accept in outbox', function () {
    $followActivity = [
        'id' => 'https://remote.example/activities/follow-1',
        'type' => 'Follow',
        'actor' => 'https://remote.example/@bob',
        'object' => 'https://example.com/@alice',
        'actor_inbox' => 'https://remote.example/@bob/inbox',
        'shared_inbox' => 'https://remote.example/inbox',
    ];

    $result = $this->inboxService->handleActivity($followActivity);
    expect($result)->toBeTrue();

    // Check follower was recorded
    expect($this->followService->isFollower('https://remote.example/@bob'))->toBeTrue();

    // Check Accept activity was queued in outbox
    $stmt = Database::getDb()->query("SELECT payload_json, target_inbox FROM activitypub_outbox WHERE status = 'pending'");
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    expect($rows)->toHaveCount(1);
    expect($rows[0]['target_inbox'])->toBe('https://remote.example/@bob/inbox');

    $acceptPayload = json_decode($rows[0]['payload_json'], true);
    expect($acceptPayload['type'])->toBe('Accept');
    expect($acceptPayload['actor'])->toBe('https://example.com/@alice');
    expect($acceptPayload['object']['id'])->toBe('https://remote.example/activities/follow-1');
});

test('InboxService handles Undo Follow activity', function () {
    $this->followService->addFollower(
        'https://remote.example/@bob',
        'https://remote.example/@bob/inbox'
    );
    expect($this->followService->isFollower('https://remote.example/@bob'))->toBeTrue();

    $undoActivity = [
        'type' => 'Undo',
        'actor' => 'https://remote.example/@bob',
        'object' => [
            'type' => 'Follow',
            'actor' => 'https://remote.example/@bob',
            'object' => 'https://example.com/@alice',
        ],
    ];

    $result = $this->inboxService->handleActivity($undoActivity);
    expect($result)->toBeTrue();
    expect($this->followService->isFollower('https://remote.example/@bob'))->toBeFalse();
});
