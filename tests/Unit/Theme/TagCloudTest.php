<?php

declare(strict_types=1);

namespace Tests\Unit\Theme;

use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Localization;
use Indieinabox\Site\Site;
use Indieinabox\Theme\ThemeData;

beforeEach(function () {
    $this->site = new Site();
    $this->site->localization = new Localization();
    $this->site->localization->defaultLang = 'en';
    $this->site->localization->lang = ['en', 'pt'];
});

test('ThemeData getTagCloudData extracts and weights tags from frontmatter and body hashtags', function () {
    $pages = new Pages();

    $p1 = Page::fromArray([
        'slug' => 'post-1',
        'title' => 'Page 1',
        'rawBody' => 'Discussing #indieweb and #fediverse topics.',
        'lang' => 'en',
        'tags' => ['tech'],
    ]);

    $p2 = Page::fromArray([
        'slug' => 'post-2',
        'title' => 'Page 2',
        'rawBody' => 'More notes on #indieweb development.',
        'lang' => 'en',
    ]);

    $p3 = Page::fromArray([
        'slug' => 'post-3',
        'title' => 'Page 3',
        'rawBody' => 'Another #indieweb entry.',
        'lang' => 'en',
    ]);

    $pages->add($p1);
    $pages->add($p2);
    $pages->add($p3);

    $data = ThemeData::getTagCloudData($pages, 'en');

    expect($data)->toHaveKeys(['indieweb', 'fediverse', 'tech']);
    expect($data['indieweb']['count'])->toBe(3);
    expect($data['fediverse']['count'])->toBe(1);
    expect($data['tech']['count'])->toBe(1);

    // Most frequent tag should have highest weight 1.0, least frequent 0.0
    expect($data['indieweb']['weight'])->toBe(1.0);
    expect($data['fediverse']['weight'])->toBe(0.0);
});

test('ThemeData getTagCloud renders semantic HTML tag cloud with dynamic font sizes', function () {
    $pages = new Pages();

    $p1 = Page::fromArray([
        'slug' => 'page-1',
        'title' => 'Page 1',
        'rawBody' => 'Learning #php and #indieweb.',
        'lang' => 'en',
    ]);
    $p2 = Page::fromArray([
        'slug' => 'page-2',
        'title' => 'Page 2',
        'rawBody' => 'More #php articles.',
        'lang' => 'en',
    ]);

    $pages->add($p1);
    $pages->add($p2);

    $html = ThemeData::getTagCloud($pages, $this->site, 'en');

    expect($html)->toContain('<div class="tag-cloud"')
                 ->toContain('href="/tag/php/"')
                 ->toContain('href="/tag/indieweb/"')
                 ->toContain('#php')
                 ->toContain('#indieweb')
                 ->toContain('font-size:');
});

test('ThemeData getTagCloud respects language filtering', function () {
    $pages = new Pages();

    $enPage = Page::fromArray([
        'slug' => 'en-post',
        'title' => 'English Post',
        'rawBody' => 'Hello #english.',
        'lang' => 'en',
    ]);
    $ptPage = Page::fromArray([
        'slug' => 'pt-post',
        'title' => 'Portuguese Post',
        'rawBody' => 'Olá #portugues.',
        'lang' => 'pt',
    ]);

    $pages->add($enPage);
    $pages->add($ptPage);

    $enCloud = ThemeData::getTagCloud($pages, $this->site, 'en');
    $ptCloud = ThemeData::getTagCloud($pages, $this->site, 'pt');

    expect($enCloud)->toContain('#english')->not->toContain('#portugues');
    expect($ptCloud)->toContain('#portugues')->not->toContain('#english');
});

test('ThemeData getTagCloud returns empty string when no tags are found', function () {
    $pages = new Pages();
    $p = Page::fromArray([
        'slug' => 'empty-post',
        'title' => 'No tags',
        'rawBody' => 'Just text without tags.',
        'lang' => 'en',
    ]);
    $pages->add($p);

    $html = ThemeData::getTagCloud($pages, $this->site, 'en');
    expect($html)->toBe('');
});
