<?php

declare(strict_types=1);

use Indieinabox\Site\Paths;


it(
    'Create Paths class with default values',
    function () {
        $paths = new Paths();

        expect($paths->baseDir)->toBe('/');
        expect($paths->outputDirHtml)->toBe('public_html');
        expect($paths->outputDirGemini)->toBe('public_gemini');
        expect($paths->outputDirGopher)->toBe('public_gopher');
        expect($paths->outputDirMedia)->toBe('public_html/media');
        expect($paths->contentDir)->toBe('content');
    }
);

it(
    'Create Paths class with custom values',
    function () {
        $paths = new Paths('/custom', 'custom_html', 'custom_gemini', 'custom_gopher', 'custom_media', 'custom_content');

        expect($paths->baseDir)->toBe('/custom');
        expect($paths->outputDirHtml)->toBe('custom_html');
        expect($paths->outputDirGemini)->toBe('custom_gemini');
        expect($paths->outputDirGopher)->toBe('custom_gopher');
        expect($paths->outputDirMedia)->toBe('custom_media');
        expect($paths->contentDir)->toBe('custom_content');
    }
);

it('resolves relative content path', function () {
    $paths = new Paths('/var/www', 'public_html', 'public_gemini', 'public_gopher', 'public_media', 'content');
    expect($paths->getContentPath())->toBe('/var/www/content');
});

it('resolves absolute content path', function () {
    $paths = new Paths('/var/www', 'public_html', 'public_gemini', 'public_gopher', 'public_media', '/opt/external_content');
    expect($paths->getContentPath())->toBe('/opt/external_content');
});
