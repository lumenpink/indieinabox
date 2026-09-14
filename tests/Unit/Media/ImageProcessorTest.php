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
