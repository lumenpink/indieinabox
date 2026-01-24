<?php

declare(strict_types=1);

use Indieinabox\Site\Support;

it(
    'Create Support class with default values',
    function () {
        $support = new Support();

        expect($support->support)->toBe(["md", "txt", "html", "htm"]);
        expect($support->defaultCategory)->toBe('General');
    }
);

it(
    'Create Support class with custom values',
    function () {
        $support = new Support(["doc", "xls"], 'None');

        expect($support->support)->toBe(["doc", "xls"]);
        expect($support->defaultCategory)->toBe('None');
    }
);
