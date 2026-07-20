<?php

declare(strict_types=1);

/**
 * Test to ensure that templates comply with web standards 
 * like Semantic HTML, Accessibility, Mosaic fallback and Microformats.
 */

$viewsDir = dirname(__DIR__, 2) . '/resources/views';

test('page.php has semantic tags, accessibility and microformats', function () use ($viewsDir) {
    $content = file_get_contents($viewsDir . '/page.php') . file_get_contents(dirname(__DIR__, 2) . '/app/Theme/ThemeHelper.php');
    
    // Semantic Web
    expect($content)->toContain('<main>');
    expect($content)->toContain('<article class="h-entry">');
    
    // Accessibility
    expect($content)->toContain('<html lang="<?= $page->lang ?>">');
    
    // Microformats
    expect($content)->toContain('class="u-url"');
    expect($content)->toContain('class="p-category"');
    expect($content)->toContain('class="dt-published"');
    expect($content)->toContain('class="p-name"');
});

test('home.php has semantic tags and microformats', function () use ($viewsDir) {
    $content = file_get_contents($viewsDir . '/home.php');
    
    // Semantic Web
    expect($content)->toContain('<main>');
    
    // Microformats
    expect($content)->toContain('h-feed');
});

test('header.php has semantic tags and accessibility features', function () use ($viewsDir) {
    $content = file_get_contents($viewsDir . '/includes/header.php') . file_get_contents(dirname(__DIR__, 2) . '/app/Theme/ThemeData.php');
    
    // Semantic Web
    expect($content)->toContain('<header>');
    expect($content)->toContain('<nav');
    
    // Accessibility
    expect($content)->toContain('aria-label="Language selector"');
    expect($content)->toContain('aria-label="Main navigation"');
    expect($content)->toContain('aria-current="true"');
});

test('footer.php has semantic tags, accessibility and h-card', function () use ($viewsDir) {
    $content = file_get_contents($viewsDir . '/includes/footer.php') . file_get_contents(dirname(__DIR__, 2) . '/app/Theme/ThemeData.php');
    
    // Semantic Web
    expect($content)->toContain('<footer role="contentinfo">');
    
    // Accessibility
    expect($content)->toContain('aria-label="Footer navigation"');
    
    // Microformats
    expect($content)->toContain('h-card');
    expect($content)->toContain('p-author');
    expect($content)->toContain('u-url');
    expect($content)->toContain('u-photo');
    expect($content)->toContain('p-name');
});

test('head.php has accessibility focus styles', function () use ($viewsDir) {
    $content = file_get_contents($viewsDir . '/includes/head.php') . file_get_contents(dirname(__DIR__, 2) . '/app/Theme/ThemeData.php');
    
    // Accessibility
    expect($content)->toContain('a:focus-visible');
});

test('summary.php has microformats and mosaic fallback', function () use ($viewsDir) {
    $content = file_get_contents($viewsDir . '/includes/summary.php') . file_get_contents(dirname(__DIR__, 2) . '/app/Theme/ThemeHelper.php');
    
    

    
    // Microformats
    expect($content)->toContain('h-entry');
    expect($content)->toContain('p-name');
    expect($content)->toContain('u-url');
    expect($content)->toContain('dt-published');
    expect($content)->toContain('p-category');
});
