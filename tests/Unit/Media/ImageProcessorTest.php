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
    // imageinterlace() returns false or 0 for non-interlaced (baseline sequential) JPEG
    expect(imageinterlace($imgLoaded))->toBeFalse();

    FileUtils::recursiveRmdir($tempDir);
});

it('embeds license comment into JPEG and GIF binaries', function () {
    $tempDir = __DIR__ . '/tmp_unit_embed_meta';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    $jpgPath = $tempDir . '/test.jpg';
    $gifPath = $tempDir . '/test.gif';

    $img = imagecreatetruecolor(50, 50);
    imagejpeg($img, $jpgPath);
    imagegif($img, $gifPath);

    $license = 'CC-BY-NC 4.0 https://creativecommons.org/licenses/by-nc/4.0/';
    ImageProcessor::embedMetadata($jpgPath, $license);
    ImageProcessor::embedMetadata($gifPath, $license);

    $jpgData = file_get_contents($jpgPath);
    expect($jpgData)->toContain('CC-BY-NC 4.0');

    $gifData = file_get_contents($gifPath);
    expect($gifData)->toContain('CC-BY-NC 4.0');

    FileUtils::recursiveRmdir($tempDir);
});
