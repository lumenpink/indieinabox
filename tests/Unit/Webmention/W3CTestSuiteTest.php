<?php

declare(strict_types=1);

use Indieinabox\Site;
use Indieinabox\Webmention\WebmentionSender;
use Indieinabox\Webmention\SourceVerifier;
use Indieinabox\Theme\ThemeData;
use Indieinabox\Core\Database;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    Database::disconnect();
    $this->tempDir = sys_get_temp_dir() . '/iiab_w3c_test_' . uniqid();
    mkdir($this->tempDir);
    Database::$dataDir = $this->tempDir;
    $dbPath = $this->tempDir . '/.indieinabox.sqlite';
    Database::connect($dbPath);
    Database::getDb()->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");
    $this->site = new Site();
});

afterEach(function () {
    /** @var \Tests\TestCase $this */
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('W3C: HTTP Link header takes priority over HTML link element', function () {
    $headers = "HTTP/1.1 200 OK\r\n" .
        "Content-Type: text/html; charset=UTF-8\r\n" .
        "Link: <https://endpoint.example.org/webmention-from-header>; rel=\"webmention\"\r\n\r\n";
    $html = '<html><head><link rel="webmention" href="https://endpoint.example.org/webmention-from-link"></head><body>Hello</body></html>';
    $effectiveUrl = 'https://example.org/target';

    $headerEndpoint = WebmentionSender::parseHeaderEndpoint($headers, $effectiveUrl);
    expect($headerEndpoint)->toBe('https://endpoint.example.org/webmention-from-header');

    $htmlEndpoint = WebmentionSender::parseHtmlEndpoint($html, $effectiveUrl);
    expect($htmlEndpoint['endpoint'])->toBe('https://endpoint.example.org/webmention-from-link');
});

test('W3C: HTML document order determines endpoint between link and a elements', function () {
    // When <link> is first
    $html1 = '<html><head><link rel="webmention" href="/endpoint-link"></head><body><a rel="webmention" href="/endpoint-a">WM</a></body></html>';
    $res1 = WebmentionSender::parseHtmlEndpoint($html1, 'https://example.com/post');
    expect($res1['endpoint'])->toBe('https://example.com/endpoint-link');
    expect($res1['tag'])->toBe('link');

    // When <a> is first
    $html2 = '<html><body><a rel="webmention" href="/endpoint-a">WM</a><link rel="webmention" href="/endpoint-link"></body></html>';
    $res2 = WebmentionSender::parseHtmlEndpoint($html2, 'https://example.com/post');
    expect($res2['endpoint'])->toBe('https://example.com/endpoint-a');
    expect($res2['tag'])->toBe('a');
});

test('W3C: multi-valued rel attributes and whitespace handling', function () {
    $html = '<html><body><a rel="author webmention pingback" href="/api/wm">Send Webmention</a></body></html>';
    $res = WebmentionSender::parseHtmlEndpoint($html, 'https://example.org/page');
    expect($res)->not->toBeNull();
    expect($res['endpoint'])->toBe('https://example.org/api/wm');

    // Must NOT match partial substrings like 'not-a-webmention'
    $invalidHtml = '<html><body><a rel="not-a-webmention" href="/fake">Fake</a></body></html>';
    $resInvalid = WebmentionSender::parseHtmlEndpoint($invalidHtml, 'https://example.org/page');
    expect($resInvalid)->toBeNull();
});

test('W3C: relative URL resolution across various forms', function () {
    $base = 'https://example.org/articles/2026/01/my-first-post/';

    // Root-relative
    expect(WebmentionSender::resolveUrl($base, '/webmention/endpoint'))
        ->toBe('https://example.org/webmention/endpoint');

    // Path-relative with trailing slash base
    expect(WebmentionSender::resolveUrl($base, 'wm-endpoint'))
        ->toBe('https://example.org/articles/2026/01/my-first-post/wm-endpoint');

    // Parent directory segments
    expect(WebmentionSender::resolveUrl($base, '../endpoint'))
        ->toBe('https://example.org/articles/2026/01/endpoint');

    expect(WebmentionSender::resolveUrl($base, '../../endpoint'))
        ->toBe('https://example.org/articles/2026/endpoint');

    expect(WebmentionSender::resolveUrl($base, '../../../endpoint'))
        ->toBe('https://example.org/articles/endpoint');

    // Query-relative
    expect(WebmentionSender::resolveUrl($base, '?endpoint=1'))
        ->toBe('https://example.org/articles/2026/01/my-first-post/?endpoint=1');

    // Protocol-relative
    expect(WebmentionSender::resolveUrl('https://example.org/post', '//cdn.example.org/wm'))
        ->toBe('https://cdn.example.org/wm');

    // Base without trailing slash
    $baseNoSlash = 'https://example.org/articles/my-post';
    expect(WebmentionSender::resolveUrl($baseNoSlash, 'wm'))
        ->toBe('https://example.org/articles/wm');
});

test('W3C: receiver verifies exact target link exists in source HTML', function () {
    $targetUrl = 'https://receiver.example.com/notes/123';
    $sourceUrl = 'https://sender.example.com/post/1';

    // Source contains exact target link in <a> href
    $validSource = '<html><body>Great post: <a href="https://receiver.example.com/notes/123">read here</a></body></html>';
    $v1 = new SourceVerifier(fn ($url) => $validSource);
    expect($v1->verifySourceLink($sourceUrl, $targetUrl)['success'])->toBeTrue();

    // Source contains target with query params in <a> href
    $targetWithQuery = 'https://receiver.example.com/notes/123?utm_source=indieweb';
    $sourceWithQuery = '<html><body><a href="https://receiver.example.com/notes/123?utm_source=indieweb">Link</a></body></html>';
    $v2 = new SourceVerifier(fn ($url) => $sourceWithQuery);
    expect($v2->verifySourceLink($sourceUrl, $targetWithQuery)['success'])->toBeTrue();

    // Target is only present as plain text, NOT as an href link
    $unlinkedSource = '<html><body>I saw https://receiver.example.com/notes/123 and liked it.</body></html>';
    $v3 = new SourceVerifier(fn ($url) => $unlinkedSource);
    expect($v3->verifySourceLink($sourceUrl, $targetUrl)['success'])->toBeFalse();

    // Target does not exist at all in source
    $unrelatedSource = '<html><body><a href="https://other.com/post">Unrelated</a></body></html>';
    $v4 = new SourceVerifier(fn ($url) => $unrelatedSource);
    expect($v4->verifySourceLink($sourceUrl, $targetUrl)['success'])->toBeFalse();
});

test('IndieWebify.me Level 1: ThemeData::getHCard renders semantic author profile', function () {
    /** @var \Tests\TestCase $this */
    Database::saveSetting('activitypub_bio', 'Passionate IndieWeb builder.');
    Database::saveSetting('activitypub_avatar', 'https://example.com/avatar.jpg');
    $this->site->metadata->author = 'Alice Wonder';
    $this->site->metadata->fqdn = 'https://alice.example.com';

    $hcardHtml = ThemeData::getHCard($this->site);

    expect($hcardHtml)->toContain('class="h-card')
        ->toContain('class="p-name u-url u-uid"')
        ->toContain('rel="me"')
        ->toContain('Alice Wonder')
        ->toContain('https://alice.example.com')
        ->toContain('class="u-photo"')
        ->toContain('https://example.com/avatar.jpg')
        ->toContain('class="p-note"')
        ->toContain('Passionate IndieWeb builder.');

    // Parse with mf2 to verify structural compliance
    $parsed = \Mf2\parse($hcardHtml, 'https://alice.example.com');
    $hcard = $parsed['items'][0];
    expect($hcard['type'])->toContain('h-card');
    expect($hcard['properties']['name'][0])->toBe('Alice Wonder');
    expect($hcard['properties']['url'][0])->toBe('https://alice.example.com/');
    $photoProp = $hcard['properties']['photo'][0];
    $photoUrl = is_array($photoProp) ? $photoProp['value'] : $photoProp;
    expect($photoUrl)->toBe('https://example.com/avatar.jpg');
    expect($hcard['properties']['note'][0])->toBe('Passionate IndieWeb builder.');
});
