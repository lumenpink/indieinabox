<?php

declare(strict_types=1);

use Indieinabox\Helper;

it('retrieves nested array keys with arrayGet', function () {
    $array = ['title' => 'My Title', 'status' => 'draft'];

    expect(Helper::arrayGet($array, 'title', 'Default'))->toBe('My Title')
        ->and(Helper::arrayGet($array, 'missing', 'Default'))->toBe('Default');
});

it('classifies page kinds correctly', function () {
    $pagePage = ['layout' => 'page'];
    $pagePost = ['layout' => 'post'];
    $pageCustom = ['layout' => 'recipe'];

    expect(Helper::kind($pagePage))->toBe(['localized' => 'Page', 'kind' => 'page'])
        ->and(Helper::kind($pagePost))->toBe(['localized' => 'Blog Post', 'kind' => 'post'])
        ->and(Helper::kind($pageCustom))->toBe(['localized' => 'recipe', 'kind' => 'recipe']);
});

it('formats localized dates', function () {
    $timestamp = 1609459200; // 2021-01-01 00:00:00 UTC
    $page = ['date' => $timestamp];

    $formatted = Helper::localizeddate($page);

    expect($formatted['long'])->toBe('January 1, 2021')
        ->and($formatted['iso'])->toBe('2021-01-01');
});
