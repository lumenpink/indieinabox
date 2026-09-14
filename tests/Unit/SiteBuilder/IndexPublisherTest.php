<?php

declare(strict_types=1);

use Indieinabox\Support\FileUtils;
use Indieinabox\Page;
use Indieinabox\Pages;
use Indieinabox\Site;
use Indieinabox\Site\Localization;
use Indieinabox\Site\Paths;
use Indieinabox\SiteBuilder\IndexPublisher;
use Indieinabox\SiteBuilder\PagePublisher;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_index_pub_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);

    $paths = new Paths($this->tempDir);
    $this->site = new Site(null, $paths);
    $this->site->paths->outputDirHtml = 'public_html';
    $this->site->paths->outputDirGemini = 'public_gemini';
    $this->site->paths->outputDirGopher = 'public_gopher';
    $this->site->paths->themeDir = 'theme';
    $this->site->localization = new Localization();
    $this->site->localization->lang = ['en'];
    $this->site->localization->defaultLang = 'en';
    $this->site->metadata->fqdn = 'https://example.com';
    $this->site->config = [
        'kinds' => [
            'note' => [
                'display_mode' => 'full_content',
                'show_in_menu' => true,
                'content_dir' => 'notes',
            ],
            'article' => [
                'display_mode' => 'default',
                'show_in_menu' => true,
                'content_dir' => 'articles',
            ],
        ],
    ];

    global $site;
    $site = $this->site;

    $this->pages = new Pages();
    $this->pagePublisher = new PagePublisher($this->site, $this->pages);
    $this->indexPublisher = new IndexPublisher($this->site, $this->pagePublisher);
});

afterEach(function () {
    FileUtils::recursiveRmdir($this->tempDir);
});

it('publishes sitemaps for active languages', function () {
    $this->site->localization->lang = ['en', 'pt'];
    $this->indexPublisher->publishSitemaps();

    // Check that sitemaps were added and generated
    $outEn = $this->tempDir . '/public_html/index/index.html';
    $outPt = $this->tempDir . '/public_html/pt/index/index.html';

    expect(file_exists($outEn))->toBeTrue();
    expect(file_exists($outPt))->toBeTrue();
});

it('publishes kind section indexes and timeline indexes', function () {
    $note = Page::fromArray([
        'title' => 'Nota 1',
        'kind' => 'note',
        'slug' => 'notes/nota-1/',
        'rawBody' => 'Conteudo nota',
        'date' => new \DateTime('2026-09-01'),
        'lang' => 'en',
    ]);
    $article = Page::fromArray([
        'title' => 'Artigo 1',
        'kind' => 'article',
        'slug' => 'articles/artigo-1/',
        'rawBody' => 'Conteudo artigo',
        'date' => new \DateTime('2026-09-02'),
        'lang' => 'en',
    ]);
    $this->pages->add($note);
    $this->pages->add($article);

    $this->indexPublisher->publishKindIndexes($this->pages);

    // Note kind uses full_content -> creates month index and kind index
    $noteIndex = $this->tempDir . '/public_html/notes/index.html';
    $noteMonthIndex = $this->tempDir . '/public_html/notes/2026-09/index.html';
    expect(file_exists($noteIndex))->toBeTrue();
    expect(file_exists($noteMonthIndex))->toBeTrue();

    // Article kind uses default section index
    $articleIndex = $this->tempDir . '/public_html/articles/index.html';
    expect(file_exists($articleIndex))->toBeTrue();
});

it('publishes taxonomy indexes for tags and flowerbeds', function () {
    $page = Page::fromArray([
        'title' => 'Post com Tag',
        'kind' => 'article',
        'slug' => 'articles/post-tag/',
        'rawBody' => 'Texto do post',
        'date' => new \DateTime('2026-09-01'),
        'lang' => 'en',
        'tags' => ['indieweb', 'php'],
    ]);
    $this->pages->add($page);

    $this->indexPublisher->publishTaxonomies($this->pages);

    $tagListing = $this->tempDir . '/public_html/tag/index.html';
    $tagIndieweb = $this->tempDir . '/public_html/tag/indieweb/index.html';
    $tagPhp = $this->tempDir . '/public_html/tag/php/index.html';

    expect(file_exists($tagListing))->toBeTrue();
    expect(file_exists($tagIndieweb))->toBeTrue();
    expect(file_exists($tagPhp))->toBeTrue();
});

it('publishes timeline static page when timeline view exists in theme', function () {
    $themeDir = $this->tempDir . '/theme/views';
    mkdir($themeDir, 0777, true);
    file_put_contents($themeDir . '/timeline.php', '<html>Timeline</html>');

    $this->indexPublisher->publishTimelineStaticPage();

    $timelineOut = $this->tempDir . '/public_html/timeline/index.html';
    expect(file_exists($timelineOut))->toBeTrue();
});
