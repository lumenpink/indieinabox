<?php

declare(strict_types=1);

use bovigo\vfs\vfsStream;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Support;

beforeEach(function () {
    global $site, $base, $parsedown, $urltranslations, $originaldaysofweek, $originalmonths, $intl;
    global $backupSite, $backupBase, $backupParsedown, $backupUrltranslations;

    if (empty($intl)) {
        include __DIR__ . '/../../data/intl.php';
    }

    vfsStream::setup('root', null, [
        'content' => [
            'blog' => [
                'my-post.md' => "---\n"
                    . "title: My Cool Post\n"
                    . "date: 1609459200\n"
                    . "tags:\n"
                    . "  - news\n"
                    . "---\n"
                    . "Hello #world, this is a test. Check out [my link](/blog/other-post).\n"
                    . "Also #anotherTag and #world."
            ]
        ]
    ]);

    $backupSite = $site ?? null;
    $backupBase = $base ?? null;
    $backupParsedown = $parsedown ?? null;
    $backupUrltranslations = $urltranslations ?? null;

    $site = new Site(
        null,
        new Paths('vfs://root', 'public', 'content'),
        null,
        null,
        new Support(['md', 'html'])
    );

    $base = 'vfs://root';
    $parsedown = new \Indieinabox\Parsedown();
    $urltranslations = [];
});

afterEach(function () {
    global $site, $base, $parsedown, $urltranslations;
    global $backupSite, $backupBase, $backupParsedown, $backupUrltranslations;

    $site = $backupSite;
    $base = $backupBase;
    $parsedown = $backupParsedown;
    $urltranslations = $backupUrltranslations;
});

it('parses markdown file and extracts tags and formats links', function () {
    $filePath = 'vfs://root/content/blog/my-post.md';
    $page = parse($filePath);

    expect($page)->toBeInstanceOf(\Indieinabox\Page::class);
    expect($page->title)->toBe('My Cool Post');
    expect($page->slug)->toBe('blog/my-post/');

    expect($page->tags)->toContain('news')
        ->and($page->tags)->toContain('world')
        ->and($page->tags)->toContain('anothertag')
        ->and(count($page->tags))->toBe(3);

    expect((string) $page->content)->toContain('<a href="/blog/other-post/">my link</a>');
});
