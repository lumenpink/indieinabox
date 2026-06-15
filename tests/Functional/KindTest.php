<?php

declare(strict_types=1);

use Indieinabox\Page;
use Indieinabox\Site;
use Indieinabox\Site\Localization;

beforeEach(function () {
    global $site, $kindspath;

    $this->originalSite = $site ?? null;
    $site = new Site(
        null,
        null,
        null,
        new Localization('en'),
        null
    );

    $this->originalKindspath = $kindspath ?? null;
    if (empty($kindspath)) {
        $kindspath = [
            "article" => ["artigos", "articles", "articulos"],
            "note" => ["notes", "notas"],
            "photo" => ["fotos", "photos"]
        ];
    }
});

afterEach(function () {
    global $site, $kindspath;
    $site = $this->originalSite;
    if ($this->originalKindspath !== null) {
        $kindspath = $this->originalKindspath;
    }
});

it('classifies custom kind if explicitly defined in page', function () {
    $pageArray = ['kind' => 'recipe', 'slug' => 'recipes/cake', 'lang' => 'en'];
    $result = kind($pageArray);
    expect($result)->toBe(['localized' => 'recipe', 'kind' => 'recipe']);

    $pageObj = Page::fromArray(['kind' => 'tutorial', 'slug' => 'tutorials/git', 'lang' => 'en']);
    $result = kind($pageObj);
    expect($result)->toBe(['localized' => 'tutorial', 'kind' => 'tutorial']);
});

it('classifies kind automatically based on slug prefix matching kindspath', function () {
    $page = ['slug' => 'articles/my-post', 'lang' => 'en'];
    $result = kind($page);
    expect($result)->toBe(['localized' => 'articles', 'kind' => 'article']);

    $page = ['slug' => 'pt-br/artigos/meu-post', 'lang' => 'pt-br'];
    $result = kind($page);
    expect($result)->toBe(['localized' => 'artigos', 'kind' => 'article']);
});

it('classifies kind as generic if no match is found', function () {
    $page = ['slug' => 'unknown/some-page', 'lang' => 'en'];
    $result = kind($page);
    expect($result)->toBe(['localized' => 'generic', 'kind' => 'generic']);
});
