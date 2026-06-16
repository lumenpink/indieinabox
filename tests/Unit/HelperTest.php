<?php

declare(strict_types=1);

use Indieinabox\Helper;

it('retrieves nested array keys with arrayGet', function () {
    $array = ['title' => 'My Title', 'status' => 'draft'];

    expect(Helper::arrayGet($array, 'title', 'Default'))->toBe('My Title')
        ->and(Helper::arrayGet($array, 'missing', 'Default'))->toBe('Default');
});

it('classifies page kinds correctly', function () {
    global $site, $kindspath;
    $backupSite = $site ?? null;
    $backupKindspath = $kindspath ?? null;

    $site = new \Indieinabox\Site(
        null,
        null,
        null,
        new \Indieinabox\Site\Localization('en'),
        null
    );
    $kindspath = [
        "article" => ["artigos", "articles", "articulos"],
        "note" => ["notes", "notas"],
        "photo" => ["fotos", "photos"]
    ];

    $pageArray = ['kind' => 'recipe', 'slug' => 'recipes/cake', 'lang' => 'en'];
    expect(Helper::kind($pageArray))->toBe(['localized' => 'recipe', 'kind' => 'recipe']);

    $pageSlugMatch = ['slug' => 'articles/my-post', 'lang' => 'en'];
    expect(Helper::kind($pageSlugMatch))->toBe(['localized' => 'articles', 'kind' => 'article']);

    $site = $backupSite;
    $kindspath = $backupKindspath;
});

it('formats localized dates', function () {
    global $originaldaysofweek, $originalmonths, $intl;
    if (empty($intl)) {
        include __DIR__ . '/../../data/intl.php';
    }

    $timestamp = 1609459200; // 2021-01-01 00:00:00 UTC
    $page = ['date' => $timestamp, 'lang' => 'en'];

    $formatted = Helper::localizeddate($page);

    expect($formatted['long'])->toBe('Thursday, December 31, 2020 at 09:00 PM')
        ->and($formatted['iso'])->toContain('2020-12-31T21:00:00-03:00');
});
