<?php

declare(strict_types=1);

namespace Tests\Integration;

use Indieinabox\ActivityPub\KeyManager;
use Indieinabox\Core\Container;
use Indieinabox\Database;
use Indieinabox\Federation\ActivityPubAdapter;
use Indieinabox\Federation\Contracts\FederationAdapter;
use Indieinabox\Federation\FederationManager;
use Indieinabox\HttpSignature;
use Indieinabox\Services\FollowService;
use Indieinabox\Services\InboxService;
use Indieinabox\Services\OutboxService;
use Indieinabox\Services\PublishPostService;
use Indieinabox\Site;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_fed_integration_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/data');
    mkdir($this->tempDir . '/content', 0777, true);

    Database::$dataDir = $this->tempDir . '/data';
    $dbPath = Database::$dataDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);

    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
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
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS activitypub_keys (
        key_id TEXT PRIMARY KEY,
        private_key TEXT NOT NULL,
        public_key TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )");

    Database::saveSetting('fqdn', 'https://fed-node.org');
    Database::saveSetting('activitypub_handle', 'sysop');

    $this->site = new Site();
    $this->site->metadata->fqdn = 'https://fed-node.org';

    $container = Container::getInstance();
    $container->flush();
    $container->instance(Site::class, $this->site);
});

afterEach(function () {
    Container::getInstance()->flush();
    Database::disconnect();
    Database::$dataDir = '';
    \Indieinabox\Support\FileUtils::recursiveRmdir($this->tempDir);
});

test('Integration: DI container autowires Federation stack and dependencies', function () {
    $container = Container::getInstance();

    $fedManager = $container->make(FederationManager::class);
    expect($fedManager)->toBeInstanceOf(FederationManager::class);
    expect($fedManager->has('activitypub'))->toBeTrue();

    $followService = $container->make(FollowService::class);
    expect($followService)->toBeInstanceOf(FollowService::class);

    $outboxService = $container->make(OutboxService::class);
    expect($outboxService)->toBeInstanceOf(OutboxService::class);

    $inboxService = $container->make(InboxService::class);
    expect($inboxService)->toBeInstanceOf(InboxService::class);
});

test('Integration: Outbox delivery signs with RSA keys and verifies via HttpSignature::verify', function () {
    $container = Container::getInstance();

    // Generate real key pair in DB
    $keyManager = new KeyManager(Database::getDb());
    $keyManager->ensureKeys('main-key');
    $publicKey = $keyManager->getPublicKey('main-key');
    expect($publicKey)->not->toBeNull();

    $capturedRequest = null;
    $transport = function (string $url, array $headers, string $body) use (&$capturedRequest) {
        $capturedRequest = [
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];
        return ['code' => 200, 'body' => '{"status":"ok"}', 'error' => ''];
    };

    $adapter = new ActivityPubAdapter($this->site, $transport);
    $fedManager = new FederationManager($this->site);
    $fedManager->register($adapter);

    $followService = new FollowService(Database::getDb());
    $outboxService = new OutboxService($fedManager, $followService, Database::getDb());

    // Enqueue an activity
    $activity = [
        'type' => 'Create',
        'actor' => 'https://fed-node.org/@sysop',
        'object' => [
            'type' => 'Note',
            'content' => 'Integration test message',
        ],
    ];
    $outboxService->enqueueDelivery($activity, 'https://remote-inbox.org/inbox');

    // Dispatch pending deliveries
    $count = $outboxService->dispatchPending(5, 'activitypub');
    expect($count)->toBe(1);

    // Verify DB status changed to sent
    $stmt = Database::getDb()->query("SELECT status FROM activitypub_outbox");
    expect($stmt->fetchColumn())->toBe('sent');

    // Verify cryptographic signature on captured request
    expect($capturedRequest)->not->toBeNull();
    $headers = $capturedRequest['headers'];
    expect($headers)->toHaveKey('Signature');
    expect($headers)->toHaveKey('Digest');

    $isValid = HttpSignature::verify(
        $headers,
        'POST',
        '/inbox',
        (string) $publicKey
    );
    expect($isValid)->toBeTrue();
});
