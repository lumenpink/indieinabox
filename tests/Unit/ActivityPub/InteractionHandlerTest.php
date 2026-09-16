<?php

declare(strict_types=1);

use Indieinabox\ActivityPub\InteractionHandler;
use Indieinabox\Site;
use Indieinabox\Site\Metadata;
use Indieinabox\Core\Database;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_interact_test_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;

    $this->site = new Site();
    $this->site->metadata = new Metadata();
    $this->site->metadata->fqdn = 'https://example.com';

    $dbPath = $this->tempDir . '/test.sqlite';
    Database::connect($dbPath);
    $this->db = Database::getDb();

    $this->db->exec("CREATE TABLE IF NOT EXISTS activitypub_outbox (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        payload_json TEXT,
        target_inbox TEXT,
        status TEXT,
        created_at INTEGER
    )");
});

afterEach(function () {
    Database::disconnect();
    if (is_dir($this->tempDir)) {
        exec("rm -rf " . escapeshellarg($this->tempDir));
    }
});

it('extracts domain correctly from handles and URLs', function () {
    $handler = new InteractionHandler($this->site, $this->db);

    expect($handler->extractDomain('@alice@mastodon.social'))->toBe('mastodon.social');
    expect($handler->extractDomain('alice@mastodon.social'))->toBe('mastodon.social');
    expect($handler->extractDomain('https://mastodon.social/'))->toBe('mastodon.social');
    expect($handler->extractDomain('http://fediverse.example.org'))->toBe('fediverse.example.org');
    expect($handler->extractDomain('instance.xyz'))->toBe('instance.xyz');
});

it('renders the interact HTML form', function () {
    $handler = new InteractionHandler($this->site, $this->db);
    $html = $handler->renderInteractHtml('https://remote.social/note/42');

    expect($html)->toContain('<form method="post">');
    expect($html)->toContain('id="instance"');
    expect($html)->toContain('<!DOCTYPE html>');
});

it('renders the authorize interaction HTML form', function () {
    $handler = new InteractionHandler($this->site, $this->db);
    $html = $handler->renderAuthorizeHtml('https://remote.social/note/42');

    expect($html)->toContain('https://remote.social/note/42');
    expect($html)->toContain('<select id="action"');
    expect($html)->toContain('value="Like"');
    expect($html)->toContain('value="Announce"');
    expect($html)->toContain('value="Create"');
});

it('processes Like interaction and creates outbox record and local markdown post', function () {
    $handler = new InteractionHandler($this->site, $this->db);

    $handler->processInteraction(
        'Like',
        'https://remote.social/note/42',
        'https://example.com/actor',
        'https://remote.social/actor',
        'https://remote.social/inbox',
        true
    );

    $stmt = $this->db->query("SELECT * FROM activitypub_outbox");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    expect(count($rows))->toBe(1);
    expect($rows[0]['target_inbox'])->toBe('https://remote.social/inbox');
    expect($rows[0]['status'])->toBe('pending');

    $payload = json_decode($rows[0]['payload_json'], true);
    expect($payload['type'])->toBe('Like');
    expect($payload['object'])->toBe('https://remote.social/note/42');

    $likesDir = $this->tempDir . '/content/likes';
    expect(is_dir($likesDir))->toBeTrue();
    $files = glob($likesDir . '/*.md');
    expect(count($files))->toBe(1);
    $md = file_get_contents($files[0]);
    expect($md)->toContain('like_of: "https://remote.social/note/42"');
});

it('processes Announce interaction and creates repost markdown', function () {
    $handler = new InteractionHandler($this->site, $this->db);

    $handler->processInteraction(
        'Announce',
        'https://remote.social/note/99',
        'https://example.com/actor',
        'https://remote.social/actor',
        'https://remote.social/inbox',
        true
    );

    $repostsDir = $this->tempDir . '/content/reposts';
    expect(is_dir($repostsDir))->toBeTrue();
    $files = glob($repostsDir . '/*.md');
    expect(count($files))->toBe(1);
    $md = file_get_contents($files[0]);
    expect($md)->toContain('repost_of: "https://remote.social/note/99"');
});

it('processes Create reply interaction with reply content and local note', function () {
    $_POST['reply_content'] = 'Nice observation!';
    $handler = new InteractionHandler($this->site, $this->db);

    $handler->processInteraction(
        'Create',
        'https://remote.social/note/100',
        'https://example.com/actor',
        'https://remote.social/actor',
        'https://remote.social/inbox',
        true
    );

    $stmt = $this->db->query("SELECT * FROM activitypub_outbox");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    expect(count($rows))->toBe(1);

    $payload = json_decode($rows[0]['payload_json'], true);
    expect($payload['type'])->toBe('Create');
    expect($payload['object']['type'])->toBe('Note');
    expect($payload['object']['inReplyTo'])->toBe('https://remote.social/note/100');
    expect($payload['object']['content'])->toContain('Nice observation!');

    $repliesDir = $this->tempDir . '/content/replies';
    expect(is_dir($repliesDir))->toBeTrue();
    $files = glob($repliesDir . '/*.md');
    expect(count($files))->toBe(1);
    $md = file_get_contents($files[0]);
    expect($md)->toContain('in_reply_to: "https://remote.social/note/100"');
    expect($md)->toContain('Nice observation!');
});
