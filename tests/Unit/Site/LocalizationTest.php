<?php

declare(strict_types=1);

use Indieinabox\Site\Localization;

it(
    'Create Localization class with default values',
    function () {
        $localization = new Localization();

        expect($localization->lang)->toBe(['en']);
        expect($localization->defaultLang)->toBe('en');
    }
);

it(
    'Create Localization class with lang as string',
    function () {
        $localization = new Localization('en');

        expect($localization->lang)->toBe(['en']);
        expect($localization->defaultLang)->toBe('en');
    }
);

it(
    'Create Localization class with lang as number',
    function () {
        $localization = new Localization(1);

        expect($localization->lang)->toBe(['1']);
        expect($localization->defaultLang)->toBe('en');
    }
);

it(
    'Create Localization class with custom values',
    function () {
        $localization = new Localization(['en', 'fr'], 'pt');

        expect($localization->lang)->toBe(['en', 'fr']);
        expect($localization->defaultLang)->toBe('pt');
    }
);
