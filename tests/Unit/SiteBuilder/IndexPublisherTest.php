<?php

declare(strict_types=1);

use Indieinabox\Support\FileUtils;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Site;
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

    $themeViewsDir = $this->tempDir . '/theme/views';
    mkdir($themeViewsDir, 0777, true);
    file_put_contents($themeViewsDir . '/page.php', '<html><body><?= $page->content ?></body></html>');

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
    if (!is_dir($themeDir)) {
        mkdir($themeDir, 0777, true);
    }
    file_put_contents($themeDir . '/timeline.php', '<html>Timeline</html>');

    $this->indexPublisher->publishTimelineStaticPage();

    $timelineOut = $this->tempDir . '/public_html/timeline/index.html';
    expect(file_exists($timelineOut))->toBeTrue();
});

it('paginates section indexes when total posts exceed per_page limit', function () {
    // Set pagination per_page to 2
    $this->site->config['pagination'] = ['per_page' => 2];

    for ($i = 1; $i <= 5; $i++) {
        $article = Page::fromArray([
            'title' => "Artigo {$i}",
            'kind' => 'article',
            'slug' => "articles/artigo-{$i}/",
            'rawBody' => "Conteudo artigo {$i}",
            'date' => new \DateTime("2026-09-0{$i}"),
            'lang' => 'en',
        ]);
        $this->pages->add($article);
    }

    $this->indexPublisher->publishKindIndexes($this->pages);

    // Page 1 (canonical)
    $page1Html = $this->tempDir . '/public_html/articles/index.html';
    expect(file_exists($page1Html))->toBeTrue();
    $p1Content = file_get_contents($page1Html);
    expect($p1Content)->toContain('Artigo 5')
        ->and($p1Content)->toContain('Artigo 4')
        ->and($p1Content)->not->toContain('Artigo 1')
        ->and($p1Content)->toContain('class="pagination"')
        ->and($p1Content)->toContain('href="page/2/"');

    // Page 2
    $page2Html = $this->tempDir . '/public_html/articles/page/2/index.html';
    expect(file_exists($page2Html))->toBeTrue();
    $p2Content = file_get_contents($page2Html);
    expect($p2Content)->toContain('Artigo 3')
        ->and($p2Content)->toContain('Artigo 2')
        ->and($p2Content)->toContain('href="../../"') // previous link pointing to page 1
        ->and($p2Content)->toContain('href="../3/"'); // next link pointing to page 3

    // Page 3
    $page3Html = $this->tempDir . '/public_html/articles/page/3/index.html';
    expect(file_exists($page3Html))->toBeTrue();
    $p3Content = file_get_contents($page3Html);
    expect($p3Content)->toContain('Artigo 1')
        ->and($p3Content)->toContain('href="../2/"');
});

it('paginates taxonomy indexes when tag posts exceed per_page limit', function () {
    $this->site->config['pagination'] = ['per_page' => 2];

    for ($i = 1; $i <= 3; $i++) {
        $post = Page::fromArray([
            'title' => "Post Tag {$i}",
            'kind' => 'article',
            'slug' => "articles/tag-post-{$i}/",
            'rawBody' => "Texto {$i}",
            'date' => new \DateTime("2026-09-0{$i}"),
            'lang' => 'en',
            'tags' => ['linux'],
        ]);
        $this->pages->add($post);
    }

    $this->indexPublisher->publishTaxonomies($this->pages);

    $tagP1 = $this->tempDir . '/public_html/tag/linux/index.html';
    $tagP2 = $this->tempDir . '/public_html/tag/linux/page/2/index.html';

    expect(file_exists($tagP1))->toBeTrue();
    expect(file_exists($tagP2))->toBeTrue();

    $p1Content = file_get_contents($tagP1);
    expect($p1Content)->toContain('Post Tag 3')
        ->and($p1Content)->toContain('class="pagination"')
        ->and($p1Content)->toContain('href="page/2/"');
});

