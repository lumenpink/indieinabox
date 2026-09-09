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

    $timestamp = 1609459200; // 2021-01-01 00:00:00 UTC
    $page = ['date' => $timestamp, 'lang' => 'en'];

    $formatted = Helper::localizeddate($page);

    expect($formatted['long'])->toBe('Thursday, December 31, 2020 at 09:00 PM')
        ->and($formatted['iso'])->toContain('2020-12-31T21:00:00-03:00');
});

it('extracts hashtags from text', function () {
    $text = "Hello world! This is a #test of the #Hashtag extraction #system. Also checking #unicodeça, #123 (should be ignored), and end of line #tag";
    $tags = Indieinabox\Helper::extractHashtags($text);

    expect($tags)->toHaveCount(5)
        ->and($tags)->toContain('test')
        ->and($tags)->toContain('hashtag')
        ->and($tags)->toContain('system')
        ->and($tags)->toContain('unicodeça')
        ->and($tags)->toContain('tag')
        ->and($tags)->not->toContain('123');
});
