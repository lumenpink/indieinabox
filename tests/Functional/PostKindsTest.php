<?php
declare(strict_types=1);

use Indieinabox\SiteBuilder;
use Indieinabox\Site;
use Indieinabox\Site\Paths;

/**
 * @property string $tempDir
 * @property Paths $paths
 * @property Site $site
 */
beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indieinabox_post_kinds_test_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/content', 0777, true);
    mkdir($this->tempDir . '/content/notes', 0777, true);
    mkdir($this->tempDir . '/content/articles', 0777, true);
    mkdir($this->tempDir . '/content/garden', 0777, true);
    
    // Create necessary views for rendering summary
    mkdir($this->tempDir . '/resources/views/includes', 0777, true);
    mkdir($this->tempDir . '/resources/views/partials', 0777, true);
    
    // Mock webmentions to make it simple
    file_put_contents($this->tempDir . '/resources/views/partials/webmentions.php', "<div class='webmentions-section'>Mocked Webmentions</div>");
    
    // Mock post-meta.php
    file_put_contents($this->tempDir . '/resources/views/includes/post-meta.php', "<div class='post-meta'>Meta</div>");
    
    // Mock kind.php
    file_put_contents($this->tempDir . '/resources/views/includes/kind.php', "<a class='the-kind' href='<?= \$page->relpath . \$page->langpath . \$page->localizedkind ?>/'>Kind Link</a>");

    // We need the REAL summary.php since we're testing its logic
    $realSummaryPhp = file_get_contents(__DIR__ . '/../../resources/views/includes/summary.php');
    file_put_contents($this->tempDir . '/resources/views/includes/summary.php', $realSummaryPhp);
    
    // Minimal page wrapper that just requires summary
    file_put_contents($this->tempDir . '/resources/views/page.php', "<html><body><?php include 'includes/summary.php'; ?></body></html>");

    $this->paths = new Paths($this->tempDir, 'public_html', 'public_gemini', 'public_gopher', 'public_media', 'content', 'resources');
    
    global $site;
    $site = new Site(null, $this->paths);
    $site->config['kinds'] = [
        'note' => ['content_dir' => 'notes'],
        'article' => ['content_dir' => 'articles'],
        'garden' => ['content_dir' => 'garden']
    ];
    // Silence twtxt output for tests by disabling feed generation if possible, or just ignore it
    $site->options->prettylinks = true;
    
    $this->site = $site;
});

afterEach(function () {
    \Indieinabox\Helper::recursiveRmdir($this->tempDir);
});

test('note kind does not render p-name title from frontmatter', function () {
    file_put_contents($this->tempDir . '/content/notes/test.md', "---\ntitle: Note Title\n---\nThis is a note.");

    $builder = new SiteBuilder($this->site);
    $builder->build();
    
    $html = file_get_contents($this->tempDir . '/public_html/notes/test/index.html');
    
    // The frontmatter title should NOT be in the HTML anywhere
    expect($html)->not->toContain('<h1 class="p-name">Note Title</h1>');
    expect($html)->toContain('<p>This is a note.</p>');
});

test('article kind does render p-name title from frontmatter if missing from body', function () {
    file_put_contents($this->tempDir . '/content/articles/test.md', "---\ntitle: Article Title\n---\nThis is an article.");

    $builder = new SiteBuilder($this->site);
    $builder->build();
    
    $html = file_get_contents($this->tempDir . '/public_html/articles/test/index.html');
    
    // The frontmatter title SHOULD be injected by summary.php
    expect($html)->toContain('<h1 class="p-name">Article Title</h1>');
    expect($html)->toContain('<p>This is an article.</p>');
});

test('h1 in markdown body gets p-name class added by renderer', function () {
    file_put_contents($this->tempDir . '/content/articles/test.md', "---\nauthor: Someone\n---\n# My Explicit Title\n\nThis is an article.");

    $builder = new SiteBuilder($this->site);
    $builder->build();
    
    $html = file_get_contents($this->tempDir . '/public_html/articles/test/index.html');
    
    // The renderer should have added class="p-name" to the h1
    expect($html)->toContain('<h1 class="p-name">My Explicit Title</h1>');
    // Summary shouldn't inject it twice
    expect(substr_count($html, 'class="p-name"'))->toBe(1);
});

test('garden kind includes webmentions', function () {
    file_put_contents($this->tempDir . '/content/garden/test.md', "---\ntitle: My Garden\n---\nGrowing plants.");

    $builder = new SiteBuilder($this->site);
    $builder->build();
    
    $html = file_get_contents($this->tempDir . '/public_html/garden/test/index.html');
    
    // webmentions.php should be included
    expect($html)->toContain("<div class='webmentions-section'>Mocked Webmentions</div>");
});

test('kind icon links to the correct localized kind archive', function () {
    file_put_contents($this->tempDir . '/content/articles/test.md', "---\ntitle: Link Test\n---\nContent");

    $builder = new SiteBuilder($this->site);
    $builder->build();
    
    $html = file_get_contents($this->tempDir . '/public_html/articles/test/index.html');
    
    // For articles, it should link to the localized kind string archive
    // $page->relpath for articles/test/ is '../../'
    // $page->localizedkind is 'articles'
    // So link is '../../articles/'
    expect($html)->toContain("href='../../articles/'");
});
