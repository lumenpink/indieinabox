<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Federation\Adapters\BookwyrmAdapter;
use Indieinabox\Federation\Adapters\LemmyAdapter;
use Indieinabox\Federation\FederationManager;
use Indieinabox\Site\Site;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_adapters_test_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;

    $dbPath = $this->tempDir . '/test.sqlite';
    Database::connect($dbPath);
    $db = Database::getDb();
    $db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    Database::saveSetting('fqdn', 'https://indie.instance');
    Database::saveSetting('activitypub_handle', 'alice');

    $this->site = new Site();
    $this->site->metadata->fqdn = 'https://indie.instance';
});

afterEach(function () {
    Database::disconnect();
    if (is_dir($this->tempDir)) {
        exec("rm -rf " . escapeshellarg($this->tempDir));
    }
});

it('registers Lemmy and Bookwyrm adapters in FederationManager by default', function () {
    $manager = new FederationManager($this->site);

    expect($manager->has('lemmy'))->toBeTrue();
    expect($manager->has('fep-1b12'))->toBeTrue();
    expect($manager->has('forum'))->toBeTrue();

    expect($manager->has('bookwyrm'))->toBeTrue();
    expect($manager->has('books'))->toBeTrue();
    expect($manager->has('reading'))->toBeTrue();

    $lemmy = $manager->get('lemmy');
    expect($lemmy)->toBeInstanceOf(LemmyAdapter::class);

    $bookwyrm = $manager->get('bookwyrm');
    expect($bookwyrm)->toBeInstanceOf(BookwyrmAdapter::class);
});

it('builds Lemmy community post, dislike, and threaded comment activities', function () {
    $adapter = new LemmyAdapter($this->site);

    // Community post
    $postActivity = $adapter->buildCommunityPostActivity(
        'https://lemmy.world/c/technology',
        'Exciting Open Source Milestones',
        'Discussion about decentralized software.',
        'https://example.com/article'
    );

    expect($postActivity['type'])->toBe('Create');
    expect($postActivity['to'])->toContain('https://lemmy.world/c/technology');
    expect($postActivity['object']['audience'])->toBe('https://lemmy.world/c/technology');
    expect($postActivity['object']['name'])->toBe('Exciting Open Source Milestones');

    // Downvote / Dislike
    $dislike = $adapter->buildDislikeActivity('https://lemmy.world/comment/555');
    expect($dislike['type'])->toBe('Dislike');
    expect($dislike['actor'])->toBe('https://indie.instance/@alice');
    expect($dislike['object'])->toBe('https://lemmy.world/comment/555');

    // Threaded reply
    $reply = $adapter->buildReplyActivity(
        'https://lemmy.world/post/100',
        'Insightful point!',
        'https://lemmy.world/comment/200'
    );
    expect($reply['type'])->toBe('Create');
    expect($reply['object']['inReplyTo'])->toBe('https://lemmy.world/comment/200');
});

it('builds Bookwyrm review and reading status activities', function () {
    $adapter = new BookwyrmAdapter($this->site);

    // Book Review
    $review = $adapter->buildReviewActivity(
        'https://bookwyrm.social/book/45678',
        'Mind-bending sci-fi',
        'A magnificent journey across the cosmos.',
        5,
        'finished'
    );

    expect($review['type'])->toBe('Create');
    expect($review['object']['type'])->toBe('Article');
    expect($review['object']['inReplyToBook'])->toBe('https://bookwyrm.social/book/45678');
    expect($review['object']['rating'])->toBe(5);
    expect($review['object']['readingStatus'])->toBe('finished');

    // Reading Status Update
    $status = $adapter->buildReadingStatusActivity(
        'https://bookwyrm.social/book/45678',
        'reading'
    );
    expect($status['type'])->toBe('Create');
    expect($status['object']['inReplyToBook'])->toBe('https://bookwyrm.social/book/45678');
    expect($status['object']['readingStatus'])->toBe('reading');
});
