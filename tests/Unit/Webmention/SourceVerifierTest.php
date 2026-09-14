<?php

declare(strict_types=1);

use Indieinabox\Webmention\SourceVerifier;

it('normalizes URLs correctly resolving relative dots and trailing slashes', function () {
    $verifier = new SourceVerifier();

    expect($verifier->normalizeUrl('https://EXAMPLE.com/foo/bar/'))->toBe('https://example.com/foo/bar');
    expect($verifier->normalizeUrl('http://site.org/a/b/../c'))->toBe('http://site.org/a/c');
    expect($verifier->normalizeUrl('http://site.org/a/./b'))->toBe('http://site.org/a/b');
});

it('matches absolute and relative URLs', function () {
    $verifier = new SourceVerifier();

    $target = 'https://example.com/posts/article';
    $source = 'https://other.com/entry/1';

    expect($verifier->urlsMatch('https://example.com/posts/article', $target, $source))->toBeTrue();
    expect($verifier->urlsMatch('HTTPS://EXAMPLE.COM/posts/article/', $target, $source))->toBeTrue();
    expect($verifier->urlsMatch('https://example.com/posts/other', $target, $source))->toBeFalse();

    // Relative to root
    expect($verifier->urlsMatch('/posts/article', 'https://other.com/posts/article', 'https://other.com/entry/1'))->toBeTrue();

    // Relative to path directory
    expect($verifier->urlsMatch('../posts/article', 'https://other.com/posts/article', 'https://other.com/entry/1'))->toBeTrue();
});

it('fails verification if source cannot be fetched', function () {
    $verifier = new SourceVerifier(fn(string $url) => false);

    $result = $verifier->verifySourceLink('https://invalid.url', 'https://example.com/target');

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Unable to fetch source URL.');
});

it('fails verification if source HTML does not link to target', function () {
    $html = '<html><head><title>No Link</title></head><body><p>Hello world without link.</p></body></html>';
    $verifier = new SourceVerifier(fn(string $url) => $html);

    $result = $verifier->verifySourceLink('https://remote.com/post', 'https://example.com/target');

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('No link to target URL found on source page.');
});

it('verifies source link and extracts title, e-content, and whostyle', function () {
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Awesome Response</title>
</head>
<body>
    <div class="h-entry">
        <div class="e-content">
            I really liked <a href="https://example.com/target">this article</a>!
        </div>
    </div>
</body>
</html>
HTML;

    $verifier = new SourceVerifier(fn(string $url) => $html);

    $result = $verifier->verifySourceLink('https://remote.com/post', 'https://example.com/target');

    expect($result['success'])->toBeTrue();
    expect($result['content']['title'])->toBe('Awesome Response');
    expect($result['content']['text'])->toContain('I really liked this article!');
});
