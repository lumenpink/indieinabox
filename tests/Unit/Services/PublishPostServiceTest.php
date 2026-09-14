<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Core\Container;
use Indieinabox\Database;
use Indieinabox\Federation\Contracts\FederationAdapter;
use Indieinabox\Federation\FederationManager;
use Indieinabox\Services\FollowService;
use Indieinabox\Services\OutboxService;
use Indieinabox\Services\PublishPostService;
use Indieinabox\Site;
use Indieinabox\Site\Paths;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_pubpost_srv_test_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/data');
    mkdir($this->tempDir . '/content', 0777, true);

    Database::$dataDir = $this->tempDir . '/data';
    $dbPath = Database::$dataDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);

    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
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

    Database::saveSetting('fqdn', 'https://publisher.example');
    Database::saveSetting('activitypub_handle', 'alice');

    $this->paths = new Paths($this->tempDir, 'public_html', 'public_gemini', 'public_gopher', 'public_media', 'content', 'resources');
    $this->site = new Site(null, $this->paths);
    $this->site->metadata->fqdn = 'https://publisher.example';
    $this->site->config = [
        'base' => '/',
        'kinds' => [
            'note' => [
                'content_dir' => 'notes',
                'has_title' => false,
                'show_on_home' => true,
                'title' => ['en' => 'Notes'],
            ],
            'article' => [
                'content_dir' => 'articles',
                'has_title' => true,
                'show_on_home' => true,
                'title' => ['en' => 'Articles'],
            ],
        ],
        'defaultlang' => 'en',
    ];

    Container::getInstance()->instance(Site::class, $this->site);

    $this->federationManager = new FederationManager($this->site);
    $this->followService = new FollowService(Database::getDb());
    $this->outboxService = new OutboxService($this->federationManager, $this->followService, Database::getDb());
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    \Indieinabox\Core\Container::getInstance()->flush();
    \Indieinabox\Support\FileUtils::recursiveRmdir($this->tempDir);
});

test('PublishPostService creates post markdown file and orchestrates build', function () {
    $service = new PublishPostService($this->site, null);

    $result = $service->publish('This is my microblog note', [], 'note');

    expect($result)->toHaveKey('slug');
    expect($result)->toHaveKey('filepath');
    expect(file_exists($result['filepath']))->toBeTrue();

    $content = file_get_contents($result['filepath']);
    expect($content)->toBe('This is my microblog note');
});

test('PublishPostService handles articles with title and broadcasts to outbox', function () {
    // Add follower to receive broadcast
    $this->followService->addFollower(
        'https://mastodon.social/users/reader',
        'https://mastodon.social/users/reader/inbox'
    );

    $service = new PublishPostService($this->site, $this->outboxService);

    $result = $service->publish(
        'Deep dive into ActivityPub architecture.',
        ['/media/photo1.jpg'],
        'article',
        'Federated Publishing'
    );

    expect(file_exists($result['filepath']))->toBeTrue();
    $content = file_get_contents($result['filepath']);
    expect($content)->toContain('title: Federated Publishing');
    expect($content)->toContain('![](/media/photo1.jpg)');

    // Verify outbox queued broadcast
    $stmt = Database::getDb()->query("SELECT payload_json, target_inbox FROM activitypub_outbox WHERE status = 'pending'");
    $queued = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    expect($queued)->toHaveCount(1);
    expect($queued[0]['target_inbox'])->toBe('https://mastodon.social/users/reader/inbox');

    $payload = json_decode($queued[0]['payload_json'], true);
    expect($payload['type'])->toBe('Create');
    expect($payload['actor'])->toBe('https://publisher.example/@alice');
    expect($payload['object']['type'])->toBe('Article');
    expect($payload['object']['name'])->toBe('Federated Publishing');
});
