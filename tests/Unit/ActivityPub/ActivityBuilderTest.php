<?php

declare(strict_types=1);

use Indieinabox\ActivityPub\ActivityBuilder;
use Indieinabox\Database;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_actbld_test_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;

    $dbPath = $this->tempDir . '/test.sqlite';
    Database::connect($dbPath);
    $db = Database::getDb();
    $db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
});

afterEach(function () {
    Database::disconnect();
    if (is_dir($this->tempDir)) {
        exec("rm -rf " . escapeshellarg($this->tempDir));
    }
});

it('builds a default Note object without title', function () {
    $object = ActivityBuilder::buildObjectForPageArray(
        'https://example.com/notes/1',
        'https://example.com/actor',
        'https://example.com',
        'Hello World!',
        null
    );

    expect($object['type'])->toBe('Note');
    expect($object['id'])->toBe('https://example.com/notes/1');
    expect($object['attributedTo'])->toBe('https://example.com/actor');
    expect($object['content'])->toBe('Hello World!');
    expect($object['to'])->toContain('https://www.w3.org/ns/activitystreams#Public');
    expect($object['cc'])->toContain('https://example.com/followers');
    expect($object)->not->toHaveKey('name');
});

it('promotes Note to Article when title is present', function () {
    $object = ActivityBuilder::buildObjectForPageArray(
        'https://example.com/articles/1',
        'https://example.com/actor',
        'https://example.com',
        '<p>Article body</p>',
        'My Great Article'
    );

    expect($object['type'])->toBe('Article');
    expect($object['name'])->toBe('My Great Article');
});

it('handles BookWyrm reading properties, reply, and syndication targets', function () {
    $metadata = [
        'read_of' => 'https://bookwyrm.social/book/999',
        'rating' => 5,
        'read_status' => 'finished',
        'reply' => 'https://mastodon.social/@user/123456',
        'syndicate_to' => ['https://lemmy.ml/c/technology', 'not-a-valid-url'],
        'photo' => '/media/photo1.png'
    ];

    $object = ActivityBuilder::buildObjectForPageArray(
        'https://example.com/reviews/1',
        'https://example.com/actor',
        'https://example.com',
        'Great read!',
        null,
        $metadata
    );

    expect($object['type'])->toBe('Article');
    expect($object['inReplyToBook'])->toBe('https://bookwyrm.social/book/999');
    expect($object['rating'])->toBe(5);
    expect($object['readingStatus'])->toBe('finished');
    expect($object['inReplyTo'])->toBe('https://mastodon.social/@user/123456');
    expect($object['to'])->toContain('https://lemmy.ml/c/technology');
    expect($object['to'])->not->toContain('not-a-valid-url');

    expect($object['attachment'])->toBeArray();
    expect($object['attachment'][0]['mediaType'])->toBe('image/png');
    expect($object['attachment'][0]['url'])->toBe('https://example.com/media/photo1.png');
});

it('extracts custom emojis when present in content and emoji files exist', function () {
    $emojiDir = $this->tempDir . '/content/media/emojis';
    mkdir($emojiDir, 0777, true);
    touch($emojiDir . '/blobcat.png');

    Database::saveSetting('contentdir', 'content');

    $object = ActivityBuilder::buildObjectForPageArray(
        'https://example.com/notes/2',
        'https://example.com/actor',
        'https://example.com',
        'Hello :blobcat: and :missing:',
        null
    );

    expect($object)->toHaveKey('tag');
    expect(count($object['tag']))->toBe(1);
    expect($object['tag'][0]['name'])->toBe(':blobcat:');
    expect($object['tag'][0]['type'])->toBe('Emoji');
    expect($object['tag'][0]['icon']['url'])->toBe('https://example.com/media/emojis/blobcat.png');
});

it('builds a Create activity correctly', function () {
    $innerObject = [
        'id' => 'https://example.com/notes/1',
        'type' => 'Note',
        'to' => ['https://www.w3.org/ns/activitystreams#Public'],
        'cc' => ['https://example.com/followers']
    ];

    $activity = ActivityBuilder::buildCreateActivity(
        'https://example.com/notes/1#activity',
        'https://example.com/actor',
        $innerObject
    );

    expect($activity['@context'])->toBe('https://www.w3.org/ns/activitystreams');
    expect($activity['type'])->toBe('Create');
    expect($activity['id'])->toBe('https://example.com/notes/1#activity');
    expect($activity['actor'])->toBe('https://example.com/actor');
    expect($activity['object'])->toBe($innerObject);
    expect($activity['to'])->toBe($innerObject['to']);
    expect($activity['cc'])->toBe($innerObject['cc']);
});

it('builds an Accept activity correctly', function () {
    $followActivity = [
        'id' => 'https://mastodon.social/follow/123',
        'type' => 'Follow',
        'actor' => 'https://mastodon.social/@user',
        'object' => 'https://example.com/actor'
    ];

    $accept = ActivityBuilder::buildAcceptActivity(
        'https://example.com/activity/accept-1',
        'https://example.com/actor',
        $followActivity
    );

    expect($accept['type'])->toBe('Accept');
    expect($accept['actor'])->toBe('https://example.com/actor');
    expect($accept['object'])->toBe($followActivity);
});

it('builds interaction activities (Like and Announce)', function () {
    $like = ActivityBuilder::buildInteractionActivity(
        'https://example.com/activity/like-1',
        'Like',
        'https://example.com/actor',
        'https://remote.social/note/123'
    );
    expect($like['type'])->toBe('Like');
    expect($like['object'])->toBe('https://remote.social/note/123');

    $announce = ActivityBuilder::buildInteractionActivity(
        'https://example.com/activity/announce-1',
        'Announce',
        'https://example.com/actor',
        'https://remote.social/note/123'
    );
    expect($announce['type'])->toBe('Announce');
    expect($announce['object'])->toBe('https://remote.social/note/123');
});
