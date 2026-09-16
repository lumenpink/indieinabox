<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Core\Database;
use Indieinabox\Services\FollowService;
use PDO;

beforeEach(function () {
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_follow_srv_test_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;

    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS activitypub_followers (
        actor_url TEXT PRIMARY KEY,
        inbox_url TEXT NOT NULL,
        shared_inbox_url TEXT
    )");
});

afterEach(function () {
    Database::disconnect();
    Database::$dataDir = '';
    \Indieinabox\Core\Container::getInstance()->flush();
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('FollowService manages followers and checks status', function () {
    $service = new FollowService(Database::getDb());

    expect($service->isFollower('https://mastodon.social/users/alice'))->toBeFalse();
    expect($service->getFollowers())->toBeEmpty();

    // Add follower
    $added = $service->addFollower(
        'https://mastodon.social/users/alice',
        'https://mastodon.social/users/alice/inbox',
        'https://mastodon.social/inbox'
    );
    expect($added)->toBeTrue();
    expect($service->isFollower('https://mastodon.social/users/alice'))->toBeTrue();

    $followers = $service->getFollowers();
    expect($followers)->toHaveCount(1);
    expect($followers[0]['actor_url'])->toBe('https://mastodon.social/users/alice');
    expect($followers[0]['inbox_url'])->toBe('https://mastodon.social/users/alice/inbox');
    expect($followers[0]['shared_inbox_url'])->toBe('https://mastodon.social/inbox');

    // Remove follower
    $removed = $service->removeFollower('https://mastodon.social/users/alice');
    expect($removed)->toBeTrue();
    expect($service->isFollower('https://mastodon.social/users/alice'))->toBeFalse();
    expect($service->getFollowers())->toBeEmpty();
});

test('FollowService returns deduplicated distinct inboxes prioritizing shared inboxes', function () {
    $service = new FollowService(Database::getDb());

    // Two users on same instance with shared inbox
    $service->addFollower(
        'https://mastodon.social/users/alice',
        'https://mastodon.social/users/alice/inbox',
        'https://mastodon.social/inbox'
    );
    $service->addFollower(
        'https://mastodon.social/users/bob',
        'https://mastodon.social/users/bob/inbox',
        'https://mastodon.social/inbox'
    );

    // User without shared inbox
    $service->addFollower(
        'https://other.example/users/carol',
        'https://other.example/users/carol/inbox',
        null
    );

    $distinctInboxes = $service->getDistinctInboxes();

    expect($distinctInboxes)->toHaveCount(2);
    expect($distinctInboxes)->toContain('https://mastodon.social/inbox');
    expect($distinctInboxes)->toContain('https://other.example/users/carol/inbox');
});
