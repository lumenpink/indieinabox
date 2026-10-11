<?php

declare(strict_types=1);

use Indieinabox\Media\ImageProcessor;
use Indieinabox\Support\FileUtils;

it('creates thumbnails and dithers images correctly', function () {
    $tempDir = __DIR__ . '/tmp_unit_media';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    $source = $tempDir . '/test.png';
    $thumb = $tempDir . '/thumb.gif';
    $dither = $tempDir . '/dither.gif';

    $img = imagecreatetruecolor(200, 200);
    $bg = imagecolorallocate($img, 100, 150, 200);
    imagefill($img, 0, 0, $bg);
    imagepng($img, $source);

    $thumbRes = ImageProcessor::createThumbnail($source, $thumb, 50, [255, 255, 255], [0, 0, 0]);
    expect($thumbRes)->toBeTrue()
        ->and(file_exists($thumb))->toBeTrue();

    $ditherRes = ImageProcessor::ditherImageToGif($source, $dither, 100, [255, 255, 255], [0, 0, 0]);
    expect($ditherRes)->toBeTrue()
        ->and(file_exists($dither))->toBeTrue();

    FileUtils::recursiveRmdir($tempDir);
});

it('converts images to Mosaic-compatible baseline JPEG without progressive scan', function () {
    $tempDir = __DIR__ . '/tmp_unit_baseline_jpg';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    $source = $tempDir . '/source.png';
    $destJpg = $tempDir . '/output.jpg';

    $img = imagecreatetruecolor(100, 100);
    $color = imagecolorallocate($img, 120, 180, 240);
    imagefill($img, 0, 0, $color);
    imagepng($img, $source);

    $converted = ImageProcessor::convertToBaselineJpeg($source, $destJpg, 85);
    expect($converted)->toBeTrue()
        ->and(file_exists($destJpg))->toBeTrue();

    // Verify JPEG format and baseline interlace flag
    $imgLoaded = imagecreatefromjpeg($destJpg);
    expect($imgLoaded)->not->toBeFalse();
    // imageinterlace() returns 0 for non-interlaced (baseline sequential) JPEG
    expect(imageinterlace($imgLoaded))->toBe(0);

    FileUtils::recursiveRmdir($tempDir);
});
