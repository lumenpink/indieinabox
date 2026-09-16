<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Federation\ActivityPubAdapter;
use Indieinabox\Site;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_ap_adapter_test_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;

    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS activitypub_keys (key_id TEXT PRIMARY KEY, private_key TEXT NOT NULL, public_key TEXT NOT NULL, created_at INTEGER NOT NULL)");

    Database::saveSetting('fqdn', 'https://myblog.example');
    Database::saveSetting('activitypub_handle', 'alice');

    $this->site = new Site();
    $this->site->metadata->fqdn = 'https://myblog.example';
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    \Indieinabox\Core\Container::getInstance()->flush();
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('ActivityPubAdapter metadata and support checks', function () {
    $adapter = new ActivityPubAdapter($this->site);

    expect($adapter->getProtocol())->toBe('activitypub');
    expect($adapter->supports('activitypub'))->toBeTrue();
    expect($adapter->supports('ap'))->toBeTrue();
    expect($adapter->supports('ACTIVITYPUB'))->toBeTrue();
    expect($adapter->supports('rss'))->toBeFalse();
});

test('ActivityPubAdapter builds like and follow activities', function () {
    $adapter = new ActivityPubAdapter($this->site);

    $like = $adapter->buildLikeActivity('https://remote.example/posts/123');
    expect($like['type'])->toBe('Like');
    expect($like['object'])->toBe('https://remote.example/posts/123');
    expect($like['actor'])->toBe('https://myblog.example/@alice');

    $follow = $adapter->buildFollowActivity('https://remote.example/@bob');
    expect($follow['type'])->toBe('Follow');
    expect($follow['object'])->toBe('https://remote.example/@bob');
    expect($follow['actor'])->toBe('https://myblog.example/@alice');
});

test('ActivityPubAdapter builds reply activity', function () {
    $adapter = new ActivityPubAdapter($this->site);

    $reply = $adapter->buildReplyActivity(
        'https://remote.example/posts/123',
        '<p>Great post!</p>',
        'https://remote.example/posts/123'
    );

    expect($reply['type'])->toBe('Create');
    expect($reply['actor'])->toBe('https://myblog.example/@alice');
    expect($reply['object']['content'])->toBe('<p>Great post!</p>');
    expect($reply['object']['inReplyTo'])->toBe('https://remote.example/posts/123');
});

test('ActivityPubAdapter parses JSON payloads and handles syntax errors', function () {
    $adapter = new ActivityPubAdapter($this->site);

    $parsed = $adapter->parseActivity('{"type":"Follow","actor":"https://remote.example/@charlie"}');
    expect($parsed)->toBeArray();
    expect($parsed['type'])->toBe('Follow');

    $invalid = $adapter->parseActivity('not-valid-json');
    expect($invalid)->toBeNull();
});

test('ActivityPubAdapter delivers activity with signed headers via transport hook', function () {
    $deliveredRequest = null;
    $transport = function (string $url, array $headers, string $body) use (&$deliveredRequest) {
        $deliveredRequest = [
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];
        return ['code' => 200, 'body' => 'OK', 'error' => ''];
    };

    $adapter = new ActivityPubAdapter($this->site, $transport);
    $payload = ['type' => 'Like', 'object' => 'https://remote.example/posts/1'];

    $result = $adapter->deliverActivity($payload, 'https://remote.example/inbox');

    expect($result)->toBeTrue();
    expect($deliveredRequest)->not->toBeNull();
    expect($deliveredRequest['url'])->toBe('https://remote.example/inbox');
    $headerKeysLower = array_change_key_case($deliveredRequest['headers'], CASE_LOWER);
    expect($headerKeysLower)->toHaveKey('signature');
    expect($headerKeysLower)->toHaveKey('digest');
});
