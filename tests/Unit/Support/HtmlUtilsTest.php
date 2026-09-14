<?php

declare(strict_types=1);

use Indieinabox\Support\HtmlUtils;

it('beautifies HTML with tidy if available or returns valid markup', function () {
    $raw = '<div><p>Hello world</p></div>';
    $beautified = HtmlUtils::beautify($raw);

    expect($beautified)->toContain('Hello world');
});

it('minifies HTML by collapsing whitespace and comments', function () {
    $raw = "<div>   \n   <p>Hello    world</p>   </div>";
    $minified = HtmlUtils::minify($raw);

    expect($minified)->toBe('<div><p>Hello world</p></div>');
});
