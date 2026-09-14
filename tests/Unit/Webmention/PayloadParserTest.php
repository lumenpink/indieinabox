<?php

declare(strict_types=1);

use Indieinabox\Webmention\PayloadParser;

it('parses an h-entry with nested h-card author details', function () {
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><title>Original Post</title></head>
<body>
    <div class="h-entry">
        <h2 class="p-name">Exciting News</h2>
        <div class="p-author h-card">
            <a class="u-url" href="https://aaronpk.com">
                <img class="u-photo" src="https://aaronpk.com/photo.jpg" alt="Aaron Parecki">
                <span class="p-name">Aaron Parecki</span>
            </a>
        </div>
        <div class="e-content">
            I am replying to <a href="https://myblog.com/posts/first">your post</a> with great joy!
        </div>
        <a class="u-in-reply-to" href="https://myblog.com/posts/first"></a>
        <time class="dt-published" datetime="2026-09-14T12:00:00Z">Sep 14, 2026</time>
    </div>
</body>
</html>
HTML;

    $parsed = PayloadParser::parse($html, 'https://aaronpk.com/post/1', 'https://myblog.com/posts/first');

    expect($parsed['author_name'])->toBe('Aaron Parecki');
    expect($parsed['author_photo'])->toBe('https://aaronpk.com/photo.jpg');
    expect($parsed['author_url'])->toBe('https://aaronpk.com');
    expect($parsed['title'])->toBe('Exciting News');
    expect($parsed['interaction_type'])->toBe('reply');
    expect($parsed['text'])->toContain('I am replying to your post with great joy!');
    expect($parsed['html'])->toContain('<a href="https://myblog.com/posts/first">your post</a>');
    expect($parsed['published'])->toBe('2026-09-14T12:00:00Z');
});

it('detects u-like-of and u-repost-of interactions', function () {
    $likeHtml = <<<HTML
<div class="h-entry">
    <a class="u-like-of" href="https://myblog.com/article">Liked</a>
    <p class="p-author h-card"><span class="p-name">Tantek</span></p>
</div>
HTML;
    $likeParsed = PayloadParser::parse($likeHtml, 'https://tantek.com/like/1', 'https://myblog.com/article');
    expect($likeParsed['interaction_type'])->toBe('like');
    expect($likeParsed['author_name'])->toBe('Tantek');

    $repostHtml = <<<HTML
<div class="h-entry">
    <a class="u-repost-of" href="https://myblog.com/article">Reposted</a>
    <p class="p-author h-card"><span class="p-name">Barnaby</span></p>
</div>
HTML;
    $repostParsed = PayloadParser::parse($repostHtml, 'https://waterpigs.co.uk/repost/1', 'https://myblog.com/article');
    expect($repostParsed['interaction_type'])->toBe('repost');
    expect($repostParsed['author_name'])->toBe('Barnaby');
});

it('detects RSVP responses (yes, no, maybe)', function () {
    $rsvpHtml = <<<HTML
<div class="h-entry">
    <a class="u-in-reply-to" href="https://myblog.com/events/indieweb-camp">Event</a>
    <data class="p-rsvp" value="yes">Attending</data>
</div>
HTML;
    $parsed = PayloadParser::parse($rsvpHtml, 'https://attendee.com/rsvp', 'https://myblog.com/events/indieweb-camp');
    expect($parsed['interaction_type'])->toBe('rsvp');
    expect($parsed['rsvp'])->toBe('yes');
});

it('gracefully falls back to domain and title when no microformats are present', function () {
    $html = <<<HTML
<html>
<head><title>Simple Blog Post</title></head>
<body>
    <p>Linking to <a href="https://myblog.com/post">your site</a> simply.</p>
</body>
</html>
HTML;
    $parsed = PayloadParser::parse($html, 'https://simpleblog.org/page.html', 'https://myblog.com/post');
    expect($parsed['author_name'])->toBe('Simple Blog Post');
    expect($parsed['interaction_type'])->toBe('webmention');
    expect($parsed['text'])->toContain('Linking to your site simply.');
});
