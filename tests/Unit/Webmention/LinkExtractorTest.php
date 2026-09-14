<?php

declare(strict_types=1);

use Indieinabox\Webmention\LinkExtractor;

it('extracts links from frontmatter interaction properties', function () {
    $frontmatter = [
        'in-reply-to' => 'https://external.com/post/1',
        'like-of' => 'https://external.com/post/2',
        'repost-of' => ['https://external.com/post/3', 'invalid-url'],
        'bookmark-of' => 'https://external.com/post/4',
        'other' => 'https://external.com/should-not-extract'
    ];

    $links = LinkExtractor::extractFromFrontmatter($frontmatter);

    expect($links)->toContain('https://external.com/post/1');
    expect($links)->toContain('https://external.com/post/2');
    expect($links)->toContain('https://external.com/post/3');
    expect($links)->toContain('https://external.com/post/4');
    expect($links)->not->toContain('invalid-url');
    expect($links)->not->toContain('https://external.com/should-not-extract');
});

it('extracts links from Markdown links, HTML tags, and bare URLs', function () {
    $content = <<<MD
Check out this [great article](https://article.com/story) and this link:
<a href="https://html-link.com/target">HTML link</a>
Also visit https://bare-url.org/info for details.
MD;

    $links = LinkExtractor::extractFromContent($content);

    expect($links)->toContain('https://article.com/story');
    expect($links)->toContain('https://html-link.com/target');
    expect($links)->toContain('https://bare-url.org/info');
});

it('filters out self-pings matching the source host', function () {
    $sourceUrl = 'https://myblog.com/posts/hello';
    $links = [
        'https://myblog.com/posts/previous',
        'https://myblog.com/about',
        'https://otherblog.org/commentary',
        'https://another.net/post'
    ];

    $filtered = LinkExtractor::filterSelfPings($sourceUrl, $links);

    expect($filtered)->not->toContain('https://myblog.com/posts/previous');
    expect($filtered)->not->toContain('https://myblog.com/about');
    expect($filtered)->toContain('https://otherblog.org/commentary');
    expect($filtered)->toContain('https://another.net/post');
});

it('extracts and deduplicates all links combined with filterSelfPings', function () {
    $sourceUrl = 'https://myblog.com/posts/new';
    $frontmatter = [
        'in-reply-to' => 'https://friend.com/post',
        'like-of' => 'https://myblog.com/own-post' // self-ping
    ];
    $content = 'Also linking to [friend](https://friend.com/post) and [wiki](https://en.wikipedia.org/wiki/IndieWeb).';

    $extracted = LinkExtractor::extractLinks($sourceUrl, $frontmatter, $content);

    expect($extracted)->toHaveCount(2);
    expect($extracted)->toContain('https://friend.com/post');
    expect($extracted)->toContain('https://en.wikipedia.org/wiki/IndieWeb');
    expect($extracted)->not->toContain('https://myblog.com/own-post');
});
