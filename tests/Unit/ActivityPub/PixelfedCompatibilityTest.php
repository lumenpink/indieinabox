<?php

declare(strict_types=1);

use Indieinabox\ActivityPub\ActivityBuilder;
use Indieinabox\Core\Database;
use Indieinabox\Microsub\ExtendedEntry;
use Indieinabox\Microsub\NormalizationAdapter;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_px_test_' . uniqid();
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

it('normalizes Pixelfed multi-image gallery post with focal points, blurhash and dimensions', function () {
    $payload = [
        'id' => 'https://pixelfed.social/p/photog/123456789',
        'type' => 'Note',
        'url' => 'https://pixelfed.social/p/photog/123456789',
        'published' => '2026-09-21T18:00:00Z',
        'content' => '<p>Beautiful sunset over the mountains 🌄 #photography</p>',
        'attachment' => [
            [
                'type' => 'Image',
                'mediaType' => 'image/jpeg',
                'url' => 'https://pixelfed.social/storage/m/_v2/1/sunset1.jpg',
                'name' => 'Mountain sunset golden hour',
                'blurhash' => 'U35;5*00_200~qIU%M%M00_2?b%M_3?b_3t7',
                'focalPoint' => [0.12, -0.45],
                'width' => 1920,
                'height' => 1080
            ],
            [
                'type' => 'Image',
                'mediaType' => 'image/png',
                'url' => 'https://pixelfed.social/storage/m/_v2/1/sunset2.png',
                'name' => 'Close up peak',
                'blurhash' => 'U67;5*00_200~qIU%M%M00_2?b%M_3?b_3t8',
                'focal_point' => [0.0, 0.0],
                'width' => 1080,
                'height' => 1080
            ]
        ]
    ];

    $entry = NormalizationAdapter::fromActivityPub($payload, $payload['content']);

    expect($entry->network)->toBe('activitypub');
    expect($entry->originServer)->toBe('pixelfed.social');
    expect($entry->photos)->toBeArray()->toHaveCount(2);

    // Verify first photo properties
    expect($entry->photos[0]['url'])->toBe('https://pixelfed.social/storage/m/_v2/1/sunset1.jpg');
    expect($entry->photos[0]['alt'])->toBe('Mountain sunset golden hour');
    expect($entry->photos[0]['blurhash'])->toBe('U35;5*00_200~qIU%M%M00_2?b%M_3?b_3t7');
    expect($entry->photos[0]['focal_point'])->toBe([0.12, -0.45]);
    expect($entry->photos[0]['width'])->toBe(1920);
    expect($entry->photos[0]['height'])->toBe(1080);
    expect($entry->photos[0]['aspect_ratio'])->toBe(1.7778);

    // Verify JF2 representation and fallback rendering
    $jf2 = $entry->toJF2Array();
    expect($jf2['photo'])->toBeArray()->toHaveCount(2);
    expect($jf2['photo'][0])->toBe('https://pixelfed.social/storage/m/_v2/1/sunset1.jpg');
    expect($jf2['_indieinabox']['photos'])->toBeArray()->toHaveCount(2);
    expect($jf2['content']['html'])->toContain('photo-gallery-fallback');
    expect($jf2['content']['html'])->toContain('Mountain sunset golden hour');
});

it('normalizes Pixelfed short video reel with poster and vertical aspect ratio', function () {
    $payload = [
        'id' => 'https://pixelfed.social/p/photog/987654321',
        'type' => 'Note',
        'url' => 'https://pixelfed.social/p/photog/987654321',
        'published' => '2026-09-21T19:00:00Z',
        'content' => '<p>Quick video recap of our hike! 🌲</p>',
        'attachment' => [
            [
                'type' => 'Video',
                'mediaType' => 'video/mp4',
                'url' => 'https://pixelfed.social/storage/m/_v2/1/reel.mp4',
                'name' => 'Hike video recap',
                'icon' => [
                    'type' => 'Image',
                    'url' => 'https://pixelfed.social/storage/m/_v2/1/poster.jpg'
                ],
                'is_reel' => true,
                'width' => 1080,
                'height' => 1920
            ]
        ]
    ];

    $entry = NormalizationAdapter::fromActivityPub($payload, $payload['content']);

    expect($entry->reels)->not->toBeNull();
    expect($entry->reels['url'])->toBe('https://pixelfed.social/storage/m/_v2/1/reel.mp4');
    expect($entry->reels['is_reel'])->toBeTrue();
    expect($entry->reels['poster'])->toBe('https://pixelfed.social/storage/m/_v2/1/poster.jpg');

    $jf2 = $entry->toJF2Array();
    expect($jf2['_indieinabox']['reels'])->not->toBeNull();
    expect($jf2['content']['html'])->toContain('reel-fallback');
    expect($jf2['content']['html'])->toContain('<video src="https://pixelfed.social/storage/m/_v2/1/reel.mp4"');
});

it('builds outgoing Pixelfed-compatible ActivityStreams object with focalPoint and blurhash', function () {
    $metadata = [
        'photo' => [
            [
                'url' => 'https://myindie.org/media/dsc_001.jpg',
                'alt' => 'Coastal horizon at dawn',
                'blurhash' => 'L6PZfSi_.AyE_3t7t7R**0o#DgR4',
                'focal_point' => [0.25, -0.15],
                'width' => 2048,
                'height' => 1365
            ],
            [
                'url' => '/media/dsc_002.png',
                'alt' => 'Macro flower',
                'focal_point' => [0.0, 0.5]
            ]
        ]
    ];

    $object = ActivityBuilder::buildObjectForPageArray(
        'https://myindie.org/photos/dawn-walk',
        'https://myindie.org/actor',
        'https://myindie.org',
        '<p>Dawn walk by the coast</p>',
        'Dawn walk',
        $metadata
    );

    expect($object['type'])->toBe('Article');
    expect($object['attachment'])->toBeArray()->toHaveCount(2);

    $att1 = $object['attachment'][0];
    expect($att1['type'])->toBe('Image');
    expect($att1['mediaType'])->toBe('image/jpeg');
    expect($att1['url'])->toBe('https://myindie.org/media/dsc_001.jpg');
    expect($att1['name'])->toBe('Coastal horizon at dawn');
    expect($att1['blurhash'])->toBe('L6PZfSi_.AyE_3t7t7R**0o#DgR4');
    expect($att1['focalPoint'])->toBe([0.25, -0.15]);
    expect($att1['width'])->toBe(2048);
    expect($att1['height'])->toBe(1365);

    $att2 = $object['attachment'][1];
    expect($att2['type'])->toBe('Image');
    expect($att2['mediaType'])->toBe('image/png');
    expect($att2['url'])->toBe('https://myindie.org/media/dsc_002.png');
    expect($att2['name'])->toBe('Macro flower');
    expect($att2['focalPoint'])->toBe([0.0, 0.5]);
});

it('builds outgoing reel / video attachment for ActivityStreams', function () {
    $metadata = [
        'reel' => [
            'url' => '/media/video_short.mp4',
            'alt' => 'Coffee pour over',
            'poster' => '/media/coffee_poster.jpg'
        ]
    ];

    $object = ActivityBuilder::buildObjectForPageArray(
        'https://myindie.org/notes/coffee',
        'https://myindie.org/actor',
        'https://myindie.org',
        '<p>Morning coffee routine</p>',
        null,
        $metadata
    );

    expect($object['type'])->toBe('Note');
    expect($object['attachment'])->toBeArray()->toHaveCount(1);
    $vid = $object['attachment'][0];
    expect($vid['type'])->toBe('Video');
    expect($vid['mediaType'])->toBe('video/mp4');
    expect($vid['url'])->toBe('https://myindie.org/media/video_short.mp4');
    expect($vid['icon']['url'])->toBe('https://myindie.org/media/coffee_poster.jpg');
});
