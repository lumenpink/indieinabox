<?php

declare(strict_types=1);

use Indieinabox\Site\Metadata;


it(
    'Create Metadata class with default values',
    function () {
        $metadata = new Metadata();

        expect($metadata->title)->toBe('My Site');
        expect($metadata->sitename)->toBe('My Site');
        expect($metadata->author)->toBe('Me');
        expect($metadata->defaultTitle)->toBe('Untitled');
        expect($metadata->fqdn)->toBe('http://localhost:8080');
    }
);

it(
    'Create Metadata class with custom values',
    function () {
        $metadata = new Metadata(
            'My Site Title',
            'My Site Name',
            'Author Name',
            'Default Title',
            'https://example.com'
        );

        expect($metadata->title)->toBe('My Site Title');
        expect($metadata->sitename)->toBe('My Site Name');
        expect($metadata->author)->toBe('Author Name');
        expect($metadata->defaultTitle)->toBe('Default Title');
        expect($metadata->fqdn)->toBe('https://example.com');
    }
);
