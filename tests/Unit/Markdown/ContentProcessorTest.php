<?php

declare(strict_types=1);

use Indieinabox\Markdown\ContentProcessor;

it('sanitizes non-string and empty tags in processTags', function () {
    $processor = new ContentProcessor();
    $content = "Body content without tags";
    $page = [
        'tags' => [
            'valid-tag',
            [], // Empty array from YAML commented tag like "- #comment"
            ['nested'],
            '',
            'No Tag',
            'another-tag',
        ],
    ];

    $result = $processor->processTags($page, $content);

    expect($result['tags'])->toBe(['valid-tag', 'another-tag']);
});
