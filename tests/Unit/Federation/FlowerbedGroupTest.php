<?php

declare(strict_types=1);

namespace Tests\Unit\Federation;

use Indieinabox\ActivityPub\KeyManager;
use Indieinabox\Core\Database;
use Indieinabox\Federation\FlowerbedGroupService;
use Indieinabox\Http\Controllers\ActivityPubController;
use Indieinabox\Repositories\SqliteActivityPubRepository;
use Indieinabox\Site\Metadata;
use Indieinabox\Site\Site;
use PDO;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_fb_unit_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;

    $this->site = new Site();
    $this->site->metadata = new Metadata();
    $this->site->metadata->fqdn = 'https://example.com';
    $this->site->metadata->title = 'Example Site';

    $dbPath = $this->tempDir . '/test.sqlite';
    Database::connect($dbPath);
    $this->db = Database::getDb();

    $this->db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    $this->db->exec("CREATE TABLE IF NOT EXISTS activitypub_keys (key_id TEXT PRIMARY KEY, public_key TEXT, private_key TEXT, created_at INTEGER DEFAULT 0)");
    $this->db->exec("CREATE TABLE IF NOT EXISTS activitypub_followers (actor_url TEXT PRIMARY KEY, inbox_url TEXT, shared_inbox_url TEXT)");
    $this->db->exec("CREATE TABLE IF NOT EXISTS activitypub_outbox (id INTEGER PRIMARY KEY AUTOINCREMENT, payload_json TEXT, target_inbox TEXT, status TEXT, created_at INTEGER)");
    $this->db->exec("CREATE TABLE IF NOT EXISTS activitypub_activities (id INTEGER PRIMARY KEY AUTOINCREMENT, activity_id TEXT UNIQUE, type TEXT, actor TEXT, payload_json TEXT, created_at INTEGER)");
    $this->db->exec("CREATE TABLE IF NOT EXISTS flowerbed_followers (flowerbed_slug TEXT NOT NULL, actor_url TEXT NOT NULL, inbox_url TEXT NOT NULL, shared_inbox_url TEXT, created_at INTEGER NOT NULL, PRIMARY KEY (flowerbed_slug, actor_url))");

    $this->keyManager = new KeyManager($this->db);
    $this->keyManager->ensureKeys();
    $this->repo = new SqliteActivityPubRepository($this->db);
    $this->service = new FlowerbedGroupService($this->site, $this->repo, $this->keyManager);
    $this->controller = new ActivityPubController($this->site, $this->db);
});

afterEach(function () {
    Database::disconnect();
    if (is_dir($this->tempDir)) {
        exec("rm -rf " . escapeshellarg($this->tempDir));
    }
});

it('generates valid Group Actor representation for a flowerbed', function () {
    $actor = $this->service->getActor('gardening-tips');

    expect($actor['type'])->toBe('Group');
    expect($actor['preferredUsername'])->toBe('gardening-tips');
    expect($actor['name'])->toBe('Gardening Tips Flowerbed');
    expect($actor['id'])->toBe('https://example.com/@flowerbed/gardening-tips');
    expect($actor['inbox'])->toBe('https://example.com/@flowerbed/gardening-tips/inbox');
    expect($actor['outbox'])->toBe('https://example.com/@flowerbed/gardening-tips/outbox');
    expect($actor['followers'])->toBe('https://example.com/@flowerbed/gardening-tips/followers');
    expect($actor['publicKey']['publicKeyPem'])->toContain('-----BEGIN PUBLIC KEY-----');
});

it('resolves flowerbed group actors via WebFinger', function () {
    $_GET['resource'] = 'acct:flowerbed-gardening-tips@example.com';

    ob_start();
    $this->controller->webfinger();
    $output = ob_get_clean();

    expect($output)->toBeJson();
    $jrd = json_decode((string) $output, true);
    expect($jrd['subject'])->toBe('acct:flowerbed-gardening-tips@example.com');
    expect($jrd['links'][0]['href'])->toBe('https://example.com/@flowerbed/gardening-tips');
});

it('handles Follow activity in flowerbed inbox, persists follower and enqueues Accept', function () {
    $follow = [
        'type' => 'Follow',
        'actor' => [
            'id' => 'https://lemmy.ml/u/johndoe',
            'inbox' => 'https://lemmy.ml/u/johndoe/inbox',
        ],
        'object' => 'https://example.com/@flowerbed/gardening-tips',
    ];

    $result = $this->service->handleInbox('gardening-tips', $follow);

    expect($result['status'])->toBe(202);
    expect($result['body']['status'])->toBe('accepted');

    // Follower persisted in DB
    $followers = $this->repo->getFlowerbedFollowerInboxes('gardening-tips');
    expect($followers)->toContain('https://lemmy.ml/u/johndoe/inbox');

    // Accept queued in outbox
    $outbox = $this->db->query("SELECT * FROM activitypub_outbox")->fetchAll(PDO::FETCH_ASSOC);
    expect(count($outbox))->toBe(1);
    expect($outbox[0]['target_inbox'])->toBe('https://lemmy.ml/u/johndoe/inbox');
    $payload = json_decode((string) $outbox[0]['payload_json'], true);
    expect($payload['type'])->toBe('Accept');
    expect($payload['actor'])->toBe('https://example.com/@flowerbed/gardening-tips');
});

it('handles Undo Follow activity in flowerbed inbox and removes follower', function () {
    $this->repo->addFlowerbedFollower('gardening-tips', 'https://lemmy.ml/u/johndoe', 'https://lemmy.ml/u/johndoe/inbox');
    expect($this->repo->getFlowerbedFollowerInboxes('gardening-tips'))->toHaveCount(1);

    $undo = [
        'type' => 'Undo',
        'actor' => 'https://lemmy.ml/u/johndoe',
        'object' => [
            'type' => 'Follow',
            'actor' => 'https://lemmy.ml/u/johndoe',
            'object' => 'https://example.com/@flowerbed/gardening-tips',
        ],
    ];

    $result = $this->service->handleInbox('gardening-tips', $undo);
    expect($result['status'])->toBe(200);
    expect($this->repo->getFlowerbedFollowerInboxes('gardening-tips'))->toHaveCount(0);
});

it('fans out Announce activity to all group followers when post is published', function () {
    $this->repo->addFlowerbedFollower('gardening-tips', 'https://lemmy.ml/u/user1', 'https://lemmy.ml/u/user1/inbox');
    $this->repo->addFlowerbedFollower('gardening-tips', 'https://kbin.social/u/user2', 'https://kbin.social/u/user2/inbox');

    $sentCount = $this->service->fanOutPost(
        'gardening-tips',
        'https://example.com/post/pruning-tomatoes/',
        'Pruning Tomatoes in Spring'
    );

    expect($sentCount)->toBe(2);

    $outbox = $this->db->query("SELECT * FROM activitypub_outbox")->fetchAll(PDO::FETCH_ASSOC);
    expect(count($outbox))->toBe(2);
    $payload = json_decode((string) $outbox[0]['payload_json'], true);
    expect($payload['type'])->toBe('Announce');
    expect($payload['actor'])->toBe('https://example.com/@flowerbed/gardening-tips');
    expect($payload['object'])->toBe('https://example.com/post/pruning-tomatoes/');
});
