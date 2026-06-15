<?php

declare(strict_types=1);

use Indieinabox\Site\Paths;


it(
    'Create Paths class with default values',
    function () {
        $paths = new Paths();

        expect($paths->baseDir)->toBe('/');
        expect($paths->outputDir)->toBe('public');
        expect($paths->contentDir)->toBe('content');
    }
);

it(
    'Create Paths class with custom values',
    function () {
        $paths = new Paths('/custom', 'custom_site', 'custom_content');

        expect($paths->baseDir)->toBe('/custom');
        expect($paths->outputDir)->toBe('custom_site');
        expect($paths->contentDir)->toBe('custom_content');
    }
);
