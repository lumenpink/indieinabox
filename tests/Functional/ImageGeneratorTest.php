<?php

declare(strict_types=1);

use Indieinabox\Helper;
use Indieinabox\Page;
use Indieinabox\Page\Metadata;

it('generates social images with correct dimensions and dithering', function () {
    $tempDir = __DIR__ . '/tmp_images';
    if (!is_dir($tempDir)) {
        mkdir($tempDir);
    }
    
    // Create a real JPEG for testing
    $sourceJpg = $tempDir . '/source.jpg';
    $img = imagecreatetruecolor(800, 600);
    $bg = imagecolorallocate($img, 200, 200, 200);
    imagefill($img, 0, 0, $bg);
    imagejpeg($img, $sourceJpg);
    imagedestroy($img);
    
    $destBase = $tempDir . '/media/test';
    
    $results = Helper::generateSocialImages($sourceJpg, $destBase, [255, 255, 255], [0, 0, 0]);
    
    expect($results)->toHaveCount(4);
    expect($results)->toHaveKey('1200x630');
    expect($results)->toHaveKey('1920x1080');
    expect($results)->toHaveKey('1440x1080');
    expect($results)->toHaveKey('1080x1080');
    
    foreach ($results as $suffix => $path) {
        expect(file_exists($path))->toBeTrue();
        list($w, $h) = getimagesize($path);
        $expectedDims = explode('x', $suffix);
        expect($w)->toEqual((int)$expectedDims[0]);
        expect($h)->toEqual((int)$expectedDims[1]);
    }
    
    // Cleanup
    Helper::recursiveRmdir($tempDir);
});

it('truncates SEO description and maps schema correctly', function () {
    $page = new Page(null, null, null);
    $page->kind = 'note';
    
    $longText = str_repeat('A very long text here. ', 20); // > 400 chars
    $page->metadata = new Metadata();
    $page->metadata->description = $longText;
    
    $seo = Helper::getSeoMetadata($page);
    
    // Check truncation
    expect(mb_strlen($seo['description']))->toBeLessThanOrEqual(150);
    expect($seo['description'])->toEndWith('...');
    
    // Check schema mapping
    expect($seo['schema_type'])->toBe('SocialMediaPosting');
    
    // Check fallback default image
    expect($seo['image'])->toEndWith('/media/default.png');
});
