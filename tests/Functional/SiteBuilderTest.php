<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\SiteBuilder;

beforeEach(function () {
    // Setup temporary directories
    $this->tempDir = sys_get_temp_dir() . '/indieinabox_test_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/content/media', 0777, true);
    mkdir($this->tempDir . '/resources/views', 0777, true);
    mkdir($this->tempDir . '/resources/static', 0777, true);

    // Create dummy content
    file_put_contents($this->tempDir . '/content/test.md', "# Test Page\nThis is a test.");
    file_put_contents($this->tempDir . '/content/media/image.jpg', "dummy image data");
    
    // Create minimal view templates
    file_put_contents($this->tempDir . '/resources/views/page.php', "<html><body><?= \$p->content ?></body></html>");
    file_put_contents($this->tempDir . '/resources/views/index.php', "<html><body>Index: <?= \$p->content ?></body></html>");

    // Initialize Paths
    $paths = new Paths(
        $this->tempDir,
        'public_html',
        'public_gemini',
        'public_gopher',
        'public_media',
        'content',
        'resources'
    );
    $this->site = new Site(null, $paths);
    $this->builder = new SiteBuilder($this->site);
});

afterEach(function () {
    // Cleanup temporary directory
    \Indieinabox\Helper::recursiveRmdir($this->tempDir);
});

test('SiteBuilder generates output for html, gemini, gopher, and copies media', function () {
    // Act
    $this->builder->build();

    // Assert directories exist
    expect(is_dir($this->tempDir . '/public_html'))->toBeTrue();
    expect(is_dir($this->tempDir . '/public_gemini'))->toBeTrue();
    expect(is_dir($this->tempDir . '/public_gopher'))->toBeTrue();
    expect(is_dir($this->tempDir . '/public_media'))->toBeTrue();

    // Assert HTML output
    $htmlFile = $this->tempDir . '/public_html/test/index.html';
    expect(file_exists($htmlFile))->toBeTrue();
    expect(file_get_contents($htmlFile))->toContain('This is a test.');

    // Assert Gemini output
    $gmiFile = $this->tempDir . '/public_gemini/test/index.gmi';
    expect(file_exists($gmiFile))->toBeTrue();
    expect(file_get_contents($gmiFile))->toContain('This is a test.');

    // Assert Gopher output
    $gopherFile = $this->tempDir . '/public_gopher/test/gophermap';
    expect(file_exists($gopherFile))->toBeTrue();
    expect(file_get_contents($gopherFile))->toContain('This is a test.');

    // Assert Media copied
    $mediaFile = $this->tempDir . '/public_media/image.jpg';
    expect(file_exists($mediaFile))->toBeTrue();
    expect(file_get_contents($mediaFile))->toEqual('dummy image data');
});
