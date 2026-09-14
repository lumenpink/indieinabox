<?php

declare(strict_types=1);

use Indieinabox\Webmention\SourceVerifier;
use Indieinabox\Webmention\PayloadParser;

/**
 * Test vectors based on W3C Webmention Recommendation and webmention.rocks test cases.
 */

it('W3C Vector: resolves relative links against source origin', function () {
    $verifier = new SourceVerifier();

    // webmention.rocks Test 2: relative link
    expect($verifier->urlsMatch('/target', 'https://example.com/target', 'https://example.com/source'))->toBeTrue();
    expect($verifier->urlsMatch('../target', 'https://example.com/target', 'https://example.com/sub/source'))->toBeTrue();
    expect($verifier->urlsMatch('target', 'https://example.com/sub/target', 'https://example.com/sub/source'))->toBeTrue();
});

it('W3C Vector: handles query parameters and trailing slashes in target matching', function () {
    $verifier = new SourceVerifier();

    // Query parameters must be preserved and matched
    expect($verifier->urlsMatch('https://example.com/target?foo=bar', 'https://example.com/target?foo=bar', 'https://source.com'))->toBeTrue();
    expect($verifier->urlsMatch('https://example.com/target?foo=bar', 'https://example.com/target?foo=baz', 'https://source.com'))->toBeFalse();

    // Trailing slash difference normalized
    expect($verifier->urlsMatch('https://example.com/target/', 'https://example.com/target', 'https://source.com'))->toBeTrue();
    expect($verifier->urlsMatch('https://example.com/target', 'https://example.com/target/', 'https://source.com'))->toBeTrue();
});

it('webmention.rocks Vector: rejects mention if link is in plain text without href attribute', function () {
    $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <p>Check this site: https://example.com/target (not a real anchor tag)</p>
</body>
</html>
HTML;
    $verifier = new SourceVerifier(fn(string $url) => $html);
    $result = $verifier->verifySourceLink('https://source.com/post', 'https://example.com/target');

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('No link to target URL found on source page.');
});

it('webmention.rocks Vector: extracts author avatar, name, and profile URL from h-card', function () {
    $html = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <div class="h-entry">
        <div class="p-author h-card">
            <img class="u-photo" src="https://waterpigs.co.uk/photo.png" alt="Barnaby">
            <a class="p-name u-url" href="https://waterpigs.co.uk">Barnaby Walters</a>
        </div>
        <p class="e-content">
            Testing webmention support from <a href="https://example.com/article">Example</a>.
        </p>
    </div>
</body>
</html>
HTML;
    $verifier = new SourceVerifier(fn(string $url) => $html);
    $result = $verifier->verifySourceLink('https://waterpigs.co.uk/post/42', 'https://example.com/article');

    expect($result['success'])->toBeTrue();
    $content = $result['content'];
    expect($content['author_name'])->toBe('Barnaby Walters');
    expect($content['author_photo'])->toBe('https://waterpigs.co.uk/photo.png');
    expect($content['author_url'])->toBe('https://waterpigs.co.uk');
    expect($content['text'])->toContain('Testing webmention support from Example.');
});

it('webmention.rocks Vector: distinguishes between like, repost, and reply', function () {
    $target = 'https://example.com/note/100';

    // 1. Like
    $likeHtml = '<div class="h-entry"><a class="u-like-of" href="' . $target . '">Starred</a></div>';
    $likeParsed = PayloadParser::parse($likeHtml, 'https://remote.com/like', $target);
    expect($likeParsed['interaction_type'])->toBe('like');

    // 2. Repost
    $repostHtml = '<div class="h-entry"><a class="u-repost-of" href="' . $target . '">Shared</a></div>';
    $repostParsed = PayloadParser::parse($repostHtml, 'https://remote.com/repost', $target);
    expect($repostParsed['interaction_type'])->toBe('repost');

    // 3. Reply
    $replyHtml = '<div class="h-entry"><a class="u-in-reply-to" href="' . $target . '">In reply to</a><div class="e-content">Great!</div></div>';
    $replyParsed = PayloadParser::parse($replyHtml, 'https://remote.com/reply', $target);
    expect($replyParsed['interaction_type'])->toBe('reply');
});
