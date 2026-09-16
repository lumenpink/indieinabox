<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Core\Database;
use Indieinabox\Federation\Contracts\FederationAdapter;
use Indieinabox\Federation\FederationManager;
use Indieinabox\Services\FollowService;
use Indieinabox\Services\OutboxService;
use Indieinabox\Site;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_outbox_srv_test_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;

    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
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

    $this->site = new Site();
    $this->federationManager = new FederationManager($this->site);
    $this->followService = new FollowService(Database::getDb());
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    \Indieinabox\Core\Container::getInstance()->flush();
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('OutboxService enqueues delivery and broadcasts to distinct inboxes', function () {
    $outboxService = new OutboxService($this->federationManager, $this->followService, Database::getDb());

    // Enqueue single delivery
    $id = $outboxService->enqueueDelivery(['type' => 'Like'], 'https://remote.example/inbox');
    expect($id)->toBeGreaterThan(0);

    // Setup followers for broadcast
    $this->followService->addFollower('https://mastodon.social/users/1', 'https://mastodon.social/users/1/inbox', 'https://mastodon.social/inbox');
    $this->followService->addFollower('https://mastodon.social/users/2', 'https://mastodon.social/users/2/inbox', 'https://mastodon.social/inbox');
    $this->followService->addFollower('https://other.social/users/3', 'https://other.social/users/3/inbox');

    $broadcastCount = $outboxService->broadcastActivity(['type' => 'Create']);
    expect($broadcastCount)->toBe(2); // 2 distinct inboxes
});

test('OutboxService dispatches pending items using federation adapter', function () {
    $deliveredItems = [];
    $mockAdapter = new class($deliveredItems) implements FederationAdapter {
        private array $items;
        public function __construct(array &$items) { $this->items = &$items; }
        public function getProtocol(): string { return 'test-proto'; }
        public function supports(string $protocol): bool { return $protocol === 'test-proto'; }
        public function buildLikeActivity(string $targetUrl): array { return []; }
        public function buildReplyActivity(string $targetUrl, string $content, ?string $inReplyTo = null): array { return []; }
        public function buildFollowActivity(string $targetActorUri): array { return []; }
        public function deliverActivity(array|string $activity, string $destinationUrl): bool {
            $this->items[] = ['activity' => $activity, 'inbox' => $destinationUrl];
            return true;
        }
        public function parseActivity(string $payload): ?array { return null; }
    };

    $this->federationManager->register($mockAdapter);
    $outboxService = new OutboxService($this->federationManager, $this->followService, Database::getDb());

    $outboxService->enqueueDelivery(['type' => 'Note', 'content' => 'Hello'], 'https://target1.example/inbox');
    $outboxService->enqueueDelivery(['type' => 'Note', 'content' => 'World'], 'https://target2.example/inbox');

    $dispatched = $outboxService->dispatchPending(10, 'test-proto');
    expect($dispatched)->toBe(2);
    expect($deliveredItems)->toHaveCount(2);
    expect($deliveredItems[0]['inbox'])->toBe('https://target1.example/inbox');
    expect($deliveredItems[1]['inbox'])->toBe('https://target2.example/inbox');

    // Subsequent dispatch should find nothing pending
    $dispatchedAgain = $outboxService->dispatchPending(10, 'test-proto');
    expect($dispatchedAgain)->toBe(0);
});
