<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Services\MediaProxyService;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/media_proxy_test_' . uniqid('', true);
    mkdir($this->tempDir, 0755, true);
    $this->secret = 'test_secret_key_12345';
    $this->fqdn = 'https://myblog.example';
    $this->service = new MediaProxyService(
        secretKey: $this->secret,
        settings: null,
        cacheDir: $this->tempDir,
        fqdn: $this->fqdn
    );
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        exec('rm -rf ' . escapeshellarg($this->tempDir));
    }
});

describe('MediaProxyService', function () {
    it('generates signed proxy URLs for external media', function () {
        $externalUrl = 'https://mastodon.social/files/image.png';
        $proxied = $this->service->generateProxiedUrl($externalUrl);

        expect($proxied)->toStartWith('/media/proxy?url=')
            ->and($proxied)->toContain('&sig=');

        $parsed = parse_url($proxied);
        parse_str($parsed['query'] ?? '', $params);

        expect($params['url'])->toBe($externalUrl);
        expect($this->service->verifySignature($externalUrl, $params['sig']))->toBeTrue();
    });

    it('leaves local media and instance FQDN URLs unmodified', function () {
        expect($this->service->generateProxiedUrl('/media/avatar.png'))->toBe('/media/avatar.png');
        expect($this->service->generateProxiedUrl('https://myblog.example/media/photo.jpg'))->toBe('https://myblog.example/media/photo.jpg');
        expect($this->service->generateProxiedUrl('data:image/png;base64,iVBORw0KGgo'))->toBe('data:image/png;base64,iVBORw0KGgo');
        expect($this->service->generateProxiedUrl('not a url'))->toBe('not a url');
    });

    it('rejects tampered URLs or signatures', function () {
        $url = 'https://remote.server/pic.jpg';
        $proxied = $this->service->generateProxiedUrl($url);
        parse_str(parse_url($proxied, PHP_URL_QUERY) ?? '', $params);

        expect($this->service->verifySignature($url, $params['sig']))->toBeTrue();
        expect($this->service->verifySignature('https://remote.server/tampered.jpg', $params['sig']))->toBeFalse();
        expect($this->service->verifySignature($url, 'invalidsignature'))->toBeFalse();
        expect($this->service->verifySignature('', ''))->toBeFalse();
    });

    it('blocks SSRF attempts on internal, loopback, or non-http URLs', function () {
        expect($this->service->isSafeUrl('http://localhost/secret'))->toBeFalse();
        expect($this->service->isSafeUrl('http://127.0.0.1/admin'))->toBeFalse();
        expect($this->service->isSafeUrl('http://10.0.0.5/api'))->toBeFalse();
        expect($this->service->isSafeUrl('http://192.168.1.1/router'))->toBeFalse();
        expect($this->service->isSafeUrl('http://172.20.0.1/docker'))->toBeFalse();
        expect($this->service->isSafeUrl('http://169.254.169.254/latest/meta-data'))->toBeFalse();
        expect($this->service->isSafeUrl('file:///etc/passwd'))->toBeFalse();
        expect($this->service->isSafeUrl('gopher://internal/something'))->toBeFalse();
    });

    it('validates public internet IP addresses as safe', function () {
        expect($this->service->isSafeUrl('https://8.8.8.8/dns-query'))->toBeTrue();
        expect($this->service->isSafeUrl('http://1.1.1.1/'))->toBeTrue();
    });

    it('rewrites HTML media elements to use signed proxy URLs', function () {
        $html = '<p>Hello <img src="https://images.example/banner.jpg" alt="Banner"></p>' .
                '<video src="https://videos.example/clip.mp4" poster="https://images.example/thumb.jpg"></video>' .
                '<img src="/media/local_avatar.png" alt="Local">';

        $rewritten = $this->service->rewriteHtmlMedia($html);

        expect($rewritten)->toContain('/media/proxy?url=' . rawurlencode('https://images.example/banner.jpg'));
        expect($rewritten)->toContain('/media/proxy?url=' . rawurlencode('https://videos.example/clip.mp4'));
        expect($rewritten)->toContain('/media/proxy?url=' . rawurlencode('https://images.example/thumb.jpg'));
        // Local media should be preserved
        expect($rewritten)->toContain('src="/media/local_avatar.png"');
    });

    it('fetches, validates MIME, and caches external media securely', function () {
        $mockFetcher = function (string $url, string $destPath): ?array {
            file_put_contents($destPath, 'fake_png_data');
            return ['mime' => 'image/png', 'size' => 13];
        };

        $serviceWithFetcher = new MediaProxyService(
            secretKey: $this->secret,
            settings: null,
            cacheDir: $this->tempDir,
            fqdn: $this->fqdn,
            customFetcher: $mockFetcher
        );

        $testUrl = 'https://8.8.8.8/test.png';
        $result = $serviceWithFetcher->fetchAndCache($testUrl);

        expect($result)->not->toBeNull();
        expect($result['mime'])->toBe('image/png');
        expect(file_exists($result['path']))->toBeTrue();

        // Second call should return cached path without custom fetcher
        $cachedPath = $serviceWithFetcher->getCachedPath($testUrl);
        expect($cachedPath)->toBe($result['path']);

        $secondResult = $serviceWithFetcher->fetchAndCache($testUrl);
        expect($secondResult['path'])->toBe($result['path']);
    });

    it('rejects caching of non-media MIME types', function () {
        $mockHtmlFetcher = function (string $url, string $destPath): ?array {
            file_put_contents($destPath, '<script>alert(1)</script>');
            return ['mime' => 'text/html', 'size' => 25];
        };

        $serviceWithFetcher = new MediaProxyService(
            secretKey: $this->secret,
            settings: null,
            cacheDir: $this->tempDir,
            fqdn: $this->fqdn,
            customFetcher: $mockHtmlFetcher
        );

        $result = $serviceWithFetcher->fetchAndCache('https://8.8.8.8/malicious.html');
        expect($result)->toBeNull();
    });
});
