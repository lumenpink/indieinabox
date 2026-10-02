<?php

declare(strict_types=1);

use Indieinabox\Page\Page;
use Indieinabox\Services\ShortlinkService;

$cacheDir = '';

beforeEach(function () use (&$cacheDir) {
    $cacheDir = sys_get_temp_dir() . '/shortlink_suite_' . uniqid('', true);
    mkdir($cacheDir, 0777, true);
});

afterEach(function () use (&$cacheDir) {
    if (is_dir($cacheDir)) {
        array_map('unlink', glob("$cacheDir/*.*"));
        @rmdir($cacheDir);
    }
});

it('successfully generates and caches 0x0.st shortlink with correct payload and headers', function () use (&$cacheDir) {
    $capturedServer = null;
    $capturedPayload = null;
    $capturedHeaders = null;

    $httpSender = function (string $server, array $payload, array $headers) use (&$capturedServer, &$capturedPayload, &$capturedHeaders) {
        $capturedServer = $server;
        $capturedPayload = $payload;
        $capturedHeaders = $headers;
        return "https://0x0.st/f9aZ.txt\n";
    };

    $service = new ShortlinkService($cacheDir, $httpSender);

    $page = new Page(null, null, null);
    $page->slug = '2026/09/meu-post-incrivel';
    $config = [
        'enabled' => true,
        'server' => 'https://0x0.st',
        'parameter' => 'shorten',
        'auth_header' => 'X-Token',
        'auth_token' => 'secret123',
    ];

    $fqdn = 'https://lumen.pink';
    $shortlink = $service->getShortlink($page, $fqdn, $config);

    expect($shortlink)->toBe('https://0x0.st/f9aZ.txt');
    expect($capturedServer)->toBe('https://0x0.st');
    expect($capturedPayload)->toBe(['shorten' => 'https://lumen.pink/2026/09/meu-post-incrivel']);
    expect($capturedHeaders)->toContain('X-Token: secret123');

    // Verify cache file was written
    $targetUrl = 'https://lumen.pink/2026/09/meu-post-incrivel';
    $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . md5($targetUrl) . '.txt';
    expect(file_exists($cacheFile))->toBeTrue();
    expect(trim(file_get_contents($cacheFile)))->toBe('https://0x0.st/f9aZ.txt');
});

it('successfully generates and caches 2lp.in shortlink with url parameter', function () use (&$cacheDir) {
    $capturedPayload = null;

    $httpSender = function (string $server, array $payload, array $headers) use (&$capturedPayload) {
        $capturedPayload = $payload;
        return 'https://2lp.in/k9B2';
    };

    $service = new ShortlinkService($cacheDir, $httpSender);

    $page = new Page(null, null, null);
    $page->slug = 'ensaios/ciberativismo';
    $config = [
        'enabled' => true,
        'server' => 'https://2lp.in',
        'parameter' => 'url',
    ];

    $fqdn = 'https://lumen.pink';
    $shortlink = $service->getShortlink($page, $fqdn, $config);

    expect($shortlink)->toBe('https://2lp.in/k9B2');
    expect($capturedPayload)->toBe(['url' => 'https://lumen.pink/ensaios/ciberativismo']);
});

it('falls back to local deterministic hash when 0x0.st returns HTTP 429 rate limit or error', function () use (&$cacheDir) {
    $httpSender = function (string $server, array $payload, array $headers) {
        // Simulating 429 rate limit or non-url response
        return null;
    };

    $service = new ShortlinkService($cacheDir, $httpSender);

    $page = new Page(null, null, null);
    $page->slug = 'artigo-com-rate-limit';
    $config = [
        'enabled' => true,
        'server' => 'https://0x0.st',
        'parameter' => 'shorten',
    ];

    $fqdn = 'https://lumen.pink';
    $targetUrl = 'https://lumen.pink/artigo-com-rate-limit';
    $expectedLocalHash = 'https://lumen.pink/s/' . substr(md5($targetUrl), 0, 8);

    $shortlink = $service->getShortlink($page, $fqdn, $config);

    expect($shortlink)->toBe($expectedLocalHash);
});

it('falls back to local deterministic hash when remote server fails or returns HTML error', function () use (&$cacheDir) {
    $httpSender = function (string $server, array $payload, array $headers) {
        // Return 500 html page string instead of URL
        return "<html><body>500 Internal Server Error</body></html>";
    };

    $service = new ShortlinkService($cacheDir, $httpSender);

    $page = new Page(null, null, null);
    $page->slug = 'artigo-500';
    $config = [
        'enabled' => true,
        'server' => 'https://2lp.in/api',
        'parameter' => 'url',
    ];

    $fqdn = 'https://lumen.pink';
    $targetUrl = 'https://lumen.pink/artigo-500';
    $expectedLocalHash = 'https://lumen.pink/s/' . substr(md5($targetUrl), 0, 8);

    $shortlink = $service->getShortlink($page, $fqdn, $config);

    expect($shortlink)->toBe($expectedLocalHash);
});

it('uses disk cache on subsequent calls without contacting remote service again', function () use (&$cacheDir) {
    $callCount = 0;
    $httpSender = function () use (&$callCount) {
        $callCount++;
        return 'https://0x0.st/hit1.txt';
    };

    $service = new ShortlinkService($cacheDir, $httpSender);
    $page = new Page(null, null, null);
    $page->slug = 'post-cache-test';
    $config = ['enabled' => true, 'server' => 'https://0x0.st', 'parameter' => 'shorten'];

    $fqdn = 'https://lumen.pink';
    $firstCall = $service->getShortlink($page, $fqdn, $config);
    expect($firstCall)->toBe('https://0x0.st/hit1.txt');
    expect($callCount)->toBe(1);

    // Second call with same service instance or another instance with same cacheDir
    $service2 = new ShortlinkService($cacheDir, function () {
        throw new \RuntimeException('Should not be called when cached');
    });

    $secondCall = $service2->getShortlink($page, $fqdn, $config);
    expect($secondCall)->toBe('https://0x0.st/hit1.txt');
});

it('bypasses remote service and returns local hash when isDev is true', function () use (&$cacheDir) {
    $service = new ShortlinkService($cacheDir, function () {
        throw new \RuntimeException('Remote shortlink must never be called during dev mode');
    });

    $page = new Page(null, null, null);
    $page->slug = 'draft-dev-note';
    $config = ['enabled' => true, 'server' => 'https://0x0.st', 'parameter' => 'shorten'];

    $fqdn = 'http://localhost:8000';
    $shortlink = $service->getShortlink($page, $fqdn, $config, isDev: true);

    $expectedLocalHash = 'http://localhost:8000/s/' . substr(md5('http://localhost:8000/draft-dev-note'), 0, 8);
    expect($shortlink)->toBe($expectedLocalHash);
});
