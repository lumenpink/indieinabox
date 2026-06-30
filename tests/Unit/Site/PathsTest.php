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
        expect($paths->outputDirMedia)->toBe('public_media');
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
