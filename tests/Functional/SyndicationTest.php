<?php
declare(strict_types=1);

use PHPUnit\Framework\Assert;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\SiteBuilder;

beforeEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    $this->tempDir = sys_get_temp_dir() . '/indieinabox_syndication_test_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/content/post', 0777, true);

    $this->paths = new Paths($this->tempDir, 'public_html', 'public_gemini', 'public_gopher', 'public_media', 'content', 'resources');
    $this->site = new Site(null, $this->paths);
    
    // We need real views
    $this->appViewsDir = __DIR__ . '/../../resources/views';
    
    global $site;
    $site = clone $this->site;
});

afterEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    \Indieinabox\Helper::recursiveRmdir($this->tempDir);
});

it('renders syndication links in post and summary', function () {
    /** @var \Tests\TestCase|mixed $this */
    
    $yaml = "---\nkind: article\nsyndication:\n  - https://mastodon.social/@lumen/12345\n  - https://github.com/lumen\ntitle: Syndicated Post\n---\nHello syndication";
    file_put_contents($this->tempDir . '/content/post/test-synd.md', $yaml);

    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');

    $pages = iterator_to_array($builder->getPages(), false);
    $page = $pages[0];

    global $site;
    ob_start();
    include $this->appViewsDir . '/page.php';
    $pageHtml = ob_get_clean();

    expect($pageHtml)->toContain('class="u-syndication" rel="syndication"');
    expect($pageHtml)->toContain('href="https://mastodon.social/@lumen/12345"');
    expect($pageHtml)->toContain('mastodon.social');
    expect($pageHtml)->toContain('href="https://github.com/lumen"');
    expect($pageHtml)->toContain('github.com');
    
    ob_start();
    include $this->appViewsDir . '/includes/summary.php';
    $summaryHtml = ob_get_clean();

    expect($summaryHtml)->toContain('class="u-syndication" rel="syndication"');
    expect($summaryHtml)->toContain('href="https://mastodon.social/@lumen/12345"');
    expect($summaryHtml)->toContain('mastodon.social');
    expect($summaryHtml)->toContain('href="https://github.com/lumen"');
});
