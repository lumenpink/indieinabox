<?php

declare(strict_types=1);

use Indieinabox\Site;
use Indieinabox\WebmentionHandler;

$tempDir = __DIR__ . '/tmp_unit_webmention';

beforeEach(function () use ($tempDir) {
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }
});

afterEach(function () use ($tempDir) {
    if (is_dir($tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($tempDir);
    }
});

it('matches identical absolute URLs', function () {
    $site = new Site();
    $handler = new WebmentionHandler($site);

    $href = 'https://example.com/about';
    $target = 'https://example.com/about';
    $source = 'https://external.com/post';

    expect($handler->urlsMatch($href, $target, $source))->toBeTrue();
});

it('matches absolute URLs with case differences and trailing slash differences', function () {
    $site = new Site();
    $handler = new WebmentionHandler($site);

    // Case difference in scheme/host
    expect($handler->urlsMatch('HTTPS://EXAMPLE.COM/about', 'https://example.com/about/', 'https://external.com/post'))->toBeTrue();
    // Trait of path normalized (trailing slash)
    expect($handler->urlsMatch('https://example.com/about/', 'https://example.com/about', 'https://external.com/post'))->toBeTrue();
    // Case differences in path should normally be case sensitive under standard routing, but normalization handles base path case nicely
    expect($handler->urlsMatch('https://example.com/ABOUT', 'https://example.com/ABOUT/', 'https://external.com/post'))->toBeTrue();
});

it('resolves and matches relative URLs correctly', function () {
    $site = new Site();
    $handler = new WebmentionHandler($site);

    $target = 'https://example.com/about';
    $source = 'https://example.com/blog/post.html';

    // Relative to root
    expect($handler->urlsMatch('/about', $target, $source))->toBeTrue();

    // Relative to directory
    expect($handler->urlsMatch('../about', $target, $source))->toBeTrue();

    // Simple relative
    expect($handler->urlsMatch('about', 'https://example.com/blog/about', $source))->toBeTrue();
});

it('saves webmention data correctly and aggregates mentions without duplicating source', function () use ($tempDir) {
    $site = new Site();
    $site->paths->baseDir = $tempDir;
    $site->metadata->fqdn = 'https://myblog.com/';

    $handler = new WebmentionHandler($site);

    $source = 'https://otherblog.com/post1';
    $target = 'https://myblog.com/about';
    $meta = ['title' => 'Great Post!', 'text' => 'Loved reading this.'];

    $handler->saveWebmention($source, $target, $meta);

    $expectedFilename = md5('about') . '.json';
    $expectedFile = $tempDir . '/data/webmentions/' . $expectedFilename;

    expect(file_exists($expectedFile))->toBeTrue();

    $content = json_decode(file_get_contents($expectedFile), true);
    expect($content)->toHaveCount(1);
    expect($content[0]['source'])->toBe($source);
    expect($content[0]['title'])->toBe('Great Post!');
    expect($content[0]['text'])->toBe('Loved reading this.');

    // Save another webmention from a different source
    $handler->saveWebmention('https://anotherblog.com/post2', $target, ['title' => 'Reply', 'text' => 'Cool.']);
    $content = json_decode(file_get_contents($expectedFile), true);
    expect($content)->toHaveCount(2);

    // Save again from same source (updates/overwrites the existing one from that source)
    $handler->saveWebmention($source, $target, ['title' => 'Updated Great Post!', 'text' => 'Loved reading this. (v2)']);
    $content = json_decode(file_get_contents($expectedFile), true);
    expect($content)->toHaveCount(2);
    
    // The one from post1 should be updated
    $post1 = array_values(array_filter($content, fn($m) => $m['source'] === $source))[0];
    expect($post1['title'])->toBe('Updated Great Post!');
    expect($post1['text'])->toBe('Loved reading this. (v2)');
});
