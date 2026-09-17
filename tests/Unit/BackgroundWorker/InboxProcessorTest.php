<?php

declare(strict_types=1);

use Indieinabox\BackgroundWorker\InboxProcessor;
use Indieinabox\Site\Site;
use Indieinabox\Core\Database;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/inbox_proc_test_' . uniqid('', true);
    mkdir($this->tempDir, 0755, true);

    Database::disconnect();
    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/test.sqlite';
    Database::connect($dbPath);
    $this->db = Database::getDb();
    $schema = file_get_contents(dirname(__DIR__, 3) . '/database.sql');
    $this->db->exec($schema);

    $this->site = new Site();
    $this->site->metadata->fqdn = 'https://example.com';
    $this->site->paths->baseDir = $this->tempDir;
});

afterEach(function () {
    Database::disconnect();
    if (is_dir($this->tempDir)) {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

test('InboxProcessor exits cleanly when inbox queue is empty', function () {
    $processor = new InboxProcessor($this->site, $this->db);
    ob_start();
    $processor->process();
    $output = ob_get_clean();

    expect($output)->toContain('No inbox items.');
});

test('InboxProcessor processes Follow ActivityPub activity and enqueues Accept', function () {
    $followPayload = [
        'headers' => [
            'signature' => 'keyId="https://remote.social/actor#main-key",headers="(request-target) host date",signature="dummy"'
        ],
        'body' => json_encode([
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'type' => 'Follow',
            'actor' => 'https://remote.social/actor',
            'object' => 'https://example.com/actor'
        ]),
        'method' => 'POST',
        'path' => '/inbox'
    ];

    $this->db->prepare("INSERT INTO inbox_queue (type, payload_json, created_at) VALUES ('activitypub', ?, ?)")
        ->execute([json_encode($followPayload), time()]);

    $jsonFetcher = function (string $url) {
        return [
            'publicKey' => [
                'id' => 'https://remote.social/actor#main-key',
                'publicKeyPem' => 'dummy-pem'
            ]
        ];
    };

    $processor = new InboxProcessor($this->site, $this->db, null, $jsonFetcher);
    ob_start();
    $processor->process();
    ob_get_clean();

    // Check follower recorded
    $follower = $this->db->query("SELECT actor_url, inbox_url FROM activitypub_followers LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    expect($follower['actor_url'])->toBe('https://remote.social/actor');
    expect($follower['inbox_url'])->toBe('https://remote.social/actor/inbox');

    // Check outbox has Accept queued
    $outbox = $this->db->query("SELECT target_inbox, payload_json FROM activitypub_outbox LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    expect($outbox['target_inbox'])->toBe('https://remote.social/actor/inbox');
    $payload = json_decode($outbox['payload_json'], true);
    expect($payload['type'])->toBe('Accept');
});

test('InboxProcessor extracts external links to archive_queue when enabled', function () {
    $this->db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES ('webarchive_enabled', '1')")
        ->execute();

    $processor = new InboxProcessor($this->site, $this->db);
    $html = '<p>Check <a href="https://external-site.org/info">this external site</a> and <a href="https://example.com/internal">internal</a>.</p>';

    $processor->extractLinksToArchiveQueue($html);

    $items = $this->db->query("SELECT url FROM archive_queue")->fetchAll(PDO::FETCH_COLUMN);
    expect($items)->toContain('https://external-site.org/info');
    expect($items)->not->toContain('https://example.com/internal');
});

test('InboxProcessor delegates webmention ingestion to IngestInteractionServiceInterface', function () {
    $spy = new class implements \Indieinabox\Services\Contracts\IngestInteractionServiceInterface {
        public ?array $webmentionCalled = null;
        public ?array $activityCalled = null;

        public function ingest(\Indieinabox\DTO\InteractionDto $interaction): bool
        {
            return true;
        }

        public function ingestWebmention(string $source, string $target, array $verifiedContent, string $status = "pending"): \Indieinabox\DTO\InteractionDto
        {
            $this->webmentionCalled = [
                'source' => $source,
                'target' => $target,
                'verifiedContent' => $verifiedContent,
                'status' => $status,
            ];
            return \Indieinabox\DTO\InteractionDto::fromArray(['id' => 'wm-1', 'source' => $source, 'target' => $target]);
        }

        public function ingestActivity(array $activity, ?array $actorData = null, string $status = "pending"): ?\Indieinabox\DTO\InteractionDto
        {
            $this->activityCalled = [
                'activity' => $activity,
                'actorData' => $actorData,
                'status' => $status,
            ];
            return \Indieinabox\DTO\InteractionDto::fromArray(['id' => 'act-1']);
        }
    };

    $sourceHtml = '<div class="h-entry"><a class="u-url" href="https://source.com/post">Post</a><a href="https://example.com/target">reply</a><p class="e-content">Great post!</p></div>';

    $fetcher = function (string $url) use ($sourceHtml) {
        if ($url === 'https://source.com/post') {
            return $sourceHtml;
        }
        return false;
    };

    $processor = new InboxProcessor($this->site, $this->db, $fetcher, null, null, $spy);
    $processor->handleWebmention([
        'source' => 'https://source.com/post',
        'target' => 'https://example.com/target',
    ]);

    expect($spy->webmentionCalled)->not->toBeNull()
        ->and($spy->webmentionCalled['source'])->toBe('https://source.com/post')
        ->and($spy->webmentionCalled['target'])->toBe('https://example.com/target')
        ->and($spy->webmentionCalled['status'])->toBe('pending');
});

test('InboxProcessor delegates activitypub create ingestion to IngestInteractionServiceInterface', function () {
    $spy = new class implements \Indieinabox\Services\Contracts\IngestInteractionServiceInterface {
        public ?array $activityCalled = null;

        public function ingest(\Indieinabox\DTO\InteractionDto $interaction): bool
        {
            return true;
        }

        public function ingestWebmention(string $source, string $target, array $verifiedContent, string $status = "pending"): \Indieinabox\DTO\InteractionDto
        {
            return \Indieinabox\DTO\InteractionDto::fromArray(['id' => 'wm-1']);
        }

        public function ingestActivity(array $activity, ?array $actorData = null, string $status = "pending"): ?\Indieinabox\DTO\InteractionDto
        {
            $this->activityCalled = [
                'activity' => $activity,
                'actorData' => $actorData,
                'status' => $status,
            ];
            return \Indieinabox\DTO\InteractionDto::fromArray(['id' => 'act-1']);
        }
    };

    $processor = new InboxProcessor($this->site, $this->db, null, null, null, $spy);
    $processor->saveActivityPubCreate([
        'type' => 'Create',
        'actor' => 'https://remote.social/actor',
        'object' => [
            'id' => 'https://remote.social/notes/1',
            'type' => 'Note',
            'content' => 'Hello federated world',
            'inReplyTo' => 'https://example.com/posts/first',
        ],
    ]);

    expect($spy->activityCalled)->not->toBeNull()
        ->and($spy->activityCalled['activity']['type'])->toBe('Create')
        ->and($spy->activityCalled['activity']['actor'])->toBe('https://remote.social/actor')
        ->and($spy->activityCalled['status'])->toBe('pending');
});

