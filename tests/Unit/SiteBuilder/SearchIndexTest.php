<?php

declare(strict_types=1);

namespace Tests\Unit\SiteBuilder;

use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Localization;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\IndexPublisher;
use Indieinabox\SiteBuilder\PagePublisher;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_search_index_test_' . bin2hex(random_bytes(6));
    @mkdir($this->tempDir, 0755, true);

    $paths = new Paths($this->tempDir);
    $this->site = new Site(null, $paths);
    $this->site->paths->outputDirHtml = 'public_html';
    $this->site->localization = new Localization();
    $this->site->localization->defaultLang = 'en';
    $this->site->localization->lang = ['en', 'pt'];
    $this->site->metadata->fqdn = 'https://example.com';

    global $site;
    $site = $this->site;

    $this->pages = new Pages();
    $this->pagePublisher = new PagePublisher($this->site, $this->pages);
    $this->indexPublisher = new IndexPublisher($this->site, $this->pagePublisher);
});

afterEach(function () {
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        FileUtils::recursiveRmdir($this->tempDir);
    }
});

test('IndexPublisher publishes global and per-language search.json index files', function () {
    $post1 = Page::fromArray([
        'slug' => 'notes/hello-world/',
        'title' => 'Hello World',
        'kind' => 'note',
        'rawBody' => 'Welcome to IndieWeb with #indieweb and #posse hashtags.',
        'date' => new \DateTime('2026-09-21 10:00:00'),
        'lang' => 'en',
    ]);

    $post2 = Page::fromArray([
        'slug' => 'articles/sobre-mim/',
        'title' => 'Sobre Mim',
        'kind' => 'article',
        'rawBody' => 'Artigo detalhado em português com #tecnologia.',
        'date' => new \DateTime('2026-09-21 11:00:00'),
        'lang' => 'pt',
        'tags' => ['bio'],
    ]);

    $draft = Page::fromArray([
        'slug' => 'notes/draft-note/',
        'title' => 'Draft Note',
        'kind' => 'note',
        'rawBody' => 'This draft should not appear in search.',
        'lang' => 'en',
        'tags' => ['draft'],
    ]);

    $this->pages->add($post1);
    $this->pages->add($post2);
    $this->pages->add($draft);

    $this->indexPublisher->publishSearchIndex($this->pages);

    $globalSearch = $this->tempDir . '/public_html/search.json';
    $ptSearch = $this->tempDir . '/public_html/pt/search.json';

    expect(file_exists($globalSearch))->toBeTrue();
    expect(file_exists($ptSearch))->toBeTrue();

    /** @var array<int, array<string, mixed>> $globalData */
    $globalData = json_decode((string) file_get_contents($globalSearch), true);
    expect($globalData)->toBeArray();
    expect(count($globalData))->toBe(2);

    $slugs = array_column($globalData, 'slug');
    expect($slugs)->toContain('notes/hello-world/')
                  ->toContain('articles/sobre-mim/')
                  ->not->toContain('notes/draft-note/');

    // Check tags on post 1 include auto-extracted hashtags
    $post1Entry = $globalData[0]['slug'] === 'notes/hello-world/' ? $globalData[0] : $globalData[1];
    expect($post1Entry['tags'])->toContain('indieweb')->toContain('posse');
    expect($post1Entry['summary'])->toContain('Welcome to IndieWeb');

    /** @var array<int, array<string, mixed>> $ptData */
    $ptData = json_decode((string) file_get_contents($ptSearch), true);
    expect($ptData)->toBeArray();
    expect(count($ptData))->toBe(1);
    expect($ptData[0]['title'])->toBe('Sobre Mim');
    expect($ptData[0]['tags'])->toContain('bio')->toContain('tecnologia');
});
