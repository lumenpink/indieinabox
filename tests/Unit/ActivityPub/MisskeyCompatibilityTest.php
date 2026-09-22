<?php

declare(strict_types=1);

use Indieinabox\ActivityPub\ActivityBuilder;
use Indieinabox\Core\Database;
use Indieinabox\DTO\InteractionDto;
use Indieinabox\Microsub\ExtendedEntry;
use Indieinabox\Microsub\NormalizationAdapter;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_mk_test_' . uniqid();
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

it('normalizes incoming Misskey quote post into ExtendedEntry with fallback rendering', function () {
    $payload = [
        'id' => 'https://misskey.test/notes/9abcdef',
        'type' => 'Note',
        'url' => 'https://misskey.test/notes/9abcdef',
        'published' => '2026-09-21T20:00:00Z',
        'content' => '<p>Totally agree with this insightful analysis!</p>',
        '_misskey_quote' => 'https://remote.social/notes/111222',
        '_misskey_quote_object' => [
            'content' => '<p>The IndieWeb puts you in control of your digital identity.</p>',
            'attributedTo' => 'https://remote.social/users/alice'
        ]
    ];

    $entry = NormalizationAdapter::fromActivityPub($payload, $payload['content']);

    expect($entry->network)->toBe('activitypub');
    expect($entry->capabilities)->toContain('quote');
    expect($entry->quote)->not->toBeNull();
    expect($entry->quote['url'])->toBe('https://remote.social/notes/111222');
    expect($entry->quote['content'])->toContain('The IndieWeb puts you in control');
    expect($entry->quote['author'])->toBe('https://remote.social/users/alice');

    // Check JF2 serialization
    $jf2 = $entry->toJF2Array();
    expect($jf2['_indieinabox']['quote'])->not->toBeNull();
    expect($jf2['content']['html'])->toContain('misskey-quote-fallback');
    expect($jf2['content']['html'])->toContain('Quoting <a href="https://remote.social/notes/111222"');
    expect($jf2['content']['text'])->toContain('Quoting https://remote.social/notes/111222:');
});

it('normalizes incoming Misskey custom emoji reaction into ExtendedEntry and InteractionDto', function () {
    // Normalization test
    $entryPayload = [
        'id' => 'https://misskey.test/notes/333444',
        'type' => 'Note',
        'content' => '<p>Reacting post</p>',
        '_misskey_reaction' => '🎉'
    ];
    $entry = NormalizationAdapter::fromActivityPub($entryPayload, $entryPayload['content']);
    expect($entry->reaction)->toBe('🎉');

    // Ingestion / InteractionDto test
    $likeActivity = [
        'id' => 'https://misskey.test/activities/like-99',
        'type' => 'Like',
        'actor' => 'https://misskey.test/users/bob',
        'object' => 'https://myindie.org/notes/my-post',
        '_misskey_reaction' => ':blobcat_party:'
    ];

    $dto = InteractionDto::fromActivityPub($likeActivity, ['name' => 'Bob Misskey']);
    expect($dto)->not->toBeNull();
    expect($dto->getType())->toBe('like');
    expect($dto->getMetadata()['reaction'])->toBe(':blobcat_party:');
    expect($dto->getTarget())->toBe('https://myindie.org/notes/my-post');
});

it('builds outgoing Misskey-compliant quote renote activity', function () {
    $metadata = [
        'quote' => 'https://misskey.art/notes/abc888'
    ];

    $object = ActivityBuilder::buildObjectForPageArray(
        'https://myindie.org/notes/commentary',
        'https://myindie.org/@sysop',
        'https://myindie.org',
        '<p>Fascinating perspective!</p>',
        null,
        $metadata
    );

    expect($object)->toHaveKey('_misskey_quote');
    expect($object['_misskey_quote'])->toBe('https://misskey.art/notes/abc888');
    expect($object['quoteUrl'])->toBe('https://misskey.art/notes/abc888');
});
