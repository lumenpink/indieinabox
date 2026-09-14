<?php

declare(strict_types=1);

namespace Tests\Functional;

use Indieinabox\Core\Container;
use Indieinabox\Database;
use Indieinabox\Federation\ActivityPubAdapter;
use Indieinabox\Federation\FederationManager;
use Indieinabox\Services\FollowService;
use Indieinabox\Services\InboxService;
use Indieinabox\Services\OutboxService;
use Indieinabox\Services\PublishPostService;
use Indieinabox\Site;
use Indieinabox\Site\Paths;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_fed_workflow_test_' . uniqid();
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

    Database::saveSetting('fqdn', 'https://federated-node.example');
    Database::saveSetting('activitypub_handle', 'nodeadmin');

    $this->paths = new Paths($this->tempDir, 'public_html', 'public_gemini', 'public_gopher', 'public_media', 'content', 'resources');
    $this->site = new Site(null, $this->paths);
    $this->site->metadata->fqdn = 'https://federated-node.example';
    $this->site->config = [
        'base' => '/',
        'kinds' => [
            'note' => ['content_dir' => 'notes', 'has_title' => false, 'show_on_home' => true, 'title' => ['en' => 'Notes']],
            'article' => ['content_dir' => 'articles', 'has_title' => true, 'show_on_home' => true, 'title' => ['en' => 'Articles']],
        ],
        'defaultlang' => 'en',
    ];

    Container::getInstance()->instance(Site::class, $this->site);

    $this->deliveredCalls = [];
    $mockTransport = function (string $url, array $headers, string $body): array {
        $this->deliveredCalls[] = [
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];
        return ['code' => 200, 'body' => 'OK', 'error' => ''];
    };

    $this->adapter = new ActivityPubAdapter($this->site, $mockTransport);
    $this->federationManager = new FederationManager($this->site);
    $this->federationManager->register($this->adapter);

    $this->followService = new FollowService(Database::getDb());
    $this->outboxService = new OutboxService($this->federationManager, $this->followService, Database::getDb());
    $this->inboxService = new InboxService(
        $this->site,
        $this->federationManager,
        $this->followService,
        $this->outboxService,
        Database::getDb()
    );
    $this->publishService = new PublishPostService($this->site, $this->outboxService);
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    \Indieinabox\Core\Container::getInstance()->flush();
    \Indieinabox\Support\FileUtils::recursiveRmdir($this->tempDir);
});

test('Complete Federation workflow: follow -> accept -> publish -> broadcast delivery -> unfollow', function () {
    // 1. Follower arrives in inbox
    $followPayload = [
        'id' => 'https://remote.social/activities/1',
        'type' => 'Follow',
        'actor' => 'https://remote.social/users/bob',
        'object' => 'https://federated-node.example/@nodeadmin',
        'actor_inbox' => 'https://remote.social/users/bob/inbox',
        'shared_inbox' => 'https://remote.social/inbox',
    ];

    $this->inboxService->enqueue('activitypub', $followPayload);
    $processed = $this->inboxService->processPendingQueue(10);
    expect($processed)->toBe(1);
    expect($this->followService->isFollower('https://remote.social/users/bob'))->toBeTrue();

    // 2. Dispatch pending Accept delivery
    $delivered = $this->outboxService->dispatchPending(10);
    expect($delivered)->toBe(1);
    expect($this->deliveredCalls)->toHaveCount(1);
    expect($this->deliveredCalls[0]['url'])->toBe('https://remote.social/users/bob/inbox');

    $acceptData = json_decode($this->deliveredCalls[0]['body'], true);
    expect($acceptData['type'])->toBe('Accept');
    expect($acceptData['actor'])->toBe('https://federated-node.example/@nodeadmin');

    // 3. Publish a new post and verify broadcast to follower's shared inbox
    $this->deliveredCalls = [];
    $post = $this->publishService->publish('Federation workflow live test!', [], 'note');
    expect(file_exists($post['filepath']))->toBeTrue();

    // Outbox should have broadcast message for the follower
    $broadcastDelivered = $this->outboxService->dispatchPending(10);
    expect($broadcastDelivered)->toBe(1);
    expect($this->deliveredCalls)->toHaveCount(1);
    expect($this->deliveredCalls[0]['url'])->toBe('https://remote.social/inbox'); // Uses shared inbox

    $createData = json_decode($this->deliveredCalls[0]['body'], true);
    expect($createData['type'])->toBe('Create');
    expect($createData['object']['content'])->toBe('Federation workflow live test!');

    // 4. Undo Follow arrives
    $undoPayload = [
        'type' => 'Undo',
        'actor' => 'https://remote.social/users/bob',
        'object' => [
            'type' => 'Follow',
            'actor' => 'https://remote.social/users/bob',
            'object' => 'https://federated-node.example/@nodeadmin',
        ],
    ];
    $this->inboxService->enqueue('activitypub', $undoPayload);
    $this->inboxService->processPendingQueue(10);

    expect($this->followService->isFollower('https://remote.social/users/bob'))->toBeFalse();
    expect($this->followService->getDistinctInboxes())->toBeEmpty();
});
