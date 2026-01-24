<?php

declare(strict_types=1);

use Indieinabox\Site\Options;

it(
    'Create Options class with default values',
    function () {
        $options = new Options();

        expect($options->buildAll)->toBe(true);
        expect($options->dev)->toBe(false);
        expect($options->skipStatic)->toBe(false);
        expect($options->forceStaticOverride)->toBe(false);
    }
);

it(
    'Create Options class with custom values',
    function () {
        $options = new Options(false, true, true, true);

        expect($options->buildAll)->toBe(false);
        expect($options->dev)->toBe(true);
        expect($options->skipStatic)->toBe(true);
        expect($options->forceStaticOverride)->toBe(true);
    }
);
