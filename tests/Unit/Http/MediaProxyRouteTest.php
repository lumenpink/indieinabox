<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Indieinabox\Http\StaticFileServer;
use Indieinabox\Services\MediaProxyService;
use Indieinabox\Site\Site;
use Indieinabox\Site\Paths;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/media_proxy_route_test_' . uniqid('', true);
    mkdir($this->tempDir, 0755, true);
    mkdir($this->tempDir . '/public_html', 0755, true);
    mkdir($this->tempDir . '/cache', 0755, true);

    $paths = new Paths($this->tempDir);
    $paths->outputDirHtml = 'public_html';
    $this->site = new Site(null, $paths);
    $this->site->metadata->fqdn = 'https://myblog.example';
    $this->server = new StaticFileServer($this->site);

    $this->secret = 'proxy_route_test_secret';
    $this->proxy = new MediaProxyService(
        secretKey: $this->secret,
        settings: null,
        cacheDir: $this->tempDir . '/cache',
        fqdn: 'https://myblog.example'
    );

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = [];
});

afterEach(function () {
    $_GET = [];
    FileUtils::recursiveRmdir($this->tempDir);
});

describe('StaticFileServer::serveProxiedMedia', function () {
    it('returns 400 when url or sig parameters are missing', function () {
        $_GET = [];

        ob_start();
        $this->server->serveProxiedMedia($this->proxy);
        $output = ob_get_clean();

        $data = json_decode($output, true);
        expect($data['error'])->toBe('missing_params');
        expect(http_response_code())->toBe(400);
    });

    it('returns 400 when only url is provided', function () {
        $_GET = ['url' => 'https://8.8.8.8/img.png'];

        ob_start();
        $this->server->serveProxiedMedia($this->proxy);
        $output = ob_get_clean();

        $data = json_decode($output, true);
        expect($data['error'])->toBe('missing_params');
        expect(http_response_code())->toBe(400);
    });

    it('returns 403 for a tampered or invalid signature', function () {
        $_GET = [
            'url' => 'https://8.8.8.8/img.png',
            'sig' => 'invalidsignature0000',
        ];

        ob_start();
        $this->server->serveProxiedMedia($this->proxy);
        $output = ob_get_clean();

        $data = json_decode($output, true);
        expect($data['error'])->toBe('invalid_signature');
        expect(http_response_code())->toBe(403);
    });

    it('returns 404 when media cannot be fetched (SSRF-blocked URL)', function () {
        $internalUrl = 'http://127.0.0.1/secret';
        $sig = hash_hmac('sha256', $internalUrl, $this->secret);
        $_GET = ['url' => $internalUrl, 'sig' => $sig];

        ob_start();
        $this->server->serveProxiedMedia($this->proxy);
        $output = ob_get_clean();

        $data = json_decode($output, true);
        expect($data['error'])->toBe('fetch_failed');
        expect(http_response_code())->toBe(404);
    });

    it('streams cached media with correct MIME type for a valid signed request', function () {
        $testUrl = 'https://8.8.8.8/sample.png';
        // Pre-populate cache directly
        $hash = hash('sha256', $testUrl);
        $cachedFile = $this->tempDir . '/cache/' . $hash . '.png';
        file_put_contents($cachedFile, 'fake_png_data');
        file_put_contents($cachedFile . '.meta', json_encode([
            'url'  => $testUrl,
            'mime' => 'image/png',
            'size' => 13,
        ]));

        $sig = hash_hmac('sha256', $testUrl, $this->secret);
        $_GET = ['url' => $testUrl, 'sig' => $sig];

        ob_start();
        $this->server->serveProxiedMedia($this->proxy);
        $output = ob_get_clean();

        expect($output)->toBe('fake_png_data');
        expect(http_response_code())->toBe(200);
    });
});

describe('MediaProxyService integration with StaticFileServer', function () {
    it('generateProxiedUrl output can be verified by the same service', function () {
        $externalUrl = 'https://images.example.com/avatar.jpg';
        $proxied = $this->proxy->generateProxiedUrl($externalUrl);

        expect($proxied)->toStartWith('/media/proxy?url=');

        parse_str(parse_url($proxied, PHP_URL_QUERY) ?? '', $params);
        expect($this->proxy->verifySignature($params['url'], $params['sig']))->toBeTrue();
    });

    it('double-proxying is prevented', function () {
        $url = 'https://images.example/photo.png';
        $proxied = $this->proxy->generateProxiedUrl($url);
        $doubleProxied = $this->proxy->generateProxiedUrl($proxied);
        // The already-proxied URL starts with /media/proxy which is local — so it's returned as-is
        expect($doubleProxied)->toBe($proxied);
    });
});
