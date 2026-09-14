<?php

declare(strict_types=1);

use Indieinabox\Site;
use Indieinabox\Micropub\MediaHandler;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->tempDir = sys_get_temp_dir() . '/iiab_media_test_' . uniqid();
    mkdir($this->tempDir);
    $this->site = new Site();
    $this->site->paths->contentDir = $this->tempDir;
    $this->site->fqdn = 'https://example.com';
});

afterEach(function () {
    /** @var \Tests\TestCase $this */
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test('MediaHandler rejects missing file or upload error', function () {
    /** @var \Tests\TestCase $this */
    $res1 = MediaHandler::handleUpload($this->site, []);
    expect($res1['status'])->toBe(400);

    $res2 = MediaHandler::handleUpload($this->site, ['error' => UPLOAD_ERR_CANT_WRITE]);
    expect($res2['status'])->toBe(400);
});

test('MediaHandler rejects disallowed file extensions', function () {
    /** @var \Tests\TestCase $this */
    $file = [
        'name' => 'malicious.exe',
        'tmp_name' => '/tmp/test.exe',
        'error' => UPLOAD_ERR_OK,
    ];
    $res = MediaHandler::handleUpload($this->site, $file);
    expect($res['status'])->toBe(400);
    expect($res['error_description'])->toContain('unsupported file extension');
});

test('MediaHandler uploads valid image and returns Location URL', function () {
    /** @var \Tests\TestCase $this */
    $tmpFile = $this->tempDir . '/upload.jpg';
    file_put_contents($tmpFile, 'image data');

    $file = [
        'name' => 'photo.jpg',
        'tmp_name' => $tmpFile,
        'error' => UPLOAD_ERR_OK,
    ];

    $res = MediaHandler::handleUpload($this->site, $file, fn ($src, $dst) => copy($src, $dst));
    expect($res['status'])->toBe(201);
    expect($res['headers'])->toHaveKey('Location');
    expect($res['headers']['Location'])->toStartWith('https://example.com/media/');
});
