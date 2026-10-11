<?php

declare(strict_types=1);

use Indieinabox\Support\FileUtils;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Site;
use Indieinabox\Site\Paths;
use Indieinabox\SiteBuilder\SiteBuilder;
use Indieinabox\SiteBuilder\PagePublisher;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_page_pub_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);

    $paths = new Paths(
        $this->tempDir,
        'public_html',
        'public_gemini',
        'public_gopher',
        'public_media',
        'content',
        'resources'
    );
    $this->site = new Site(null, $paths);
    $this->site->metadata->sitename = 'Page Pub Blog';
    $this->site->metadata->author = 'Test Author';
    $this->site->metadata->fqdn = 'https://example.com';
    $this->site->localization->lang = ['en', 'pt'];
    $this->site->localization->defaultLang = 'en';

    // Create theme views directory and a test view
    $themeViewDir = $this->tempDir . '/theme/views';
    mkdir($themeViewDir, 0777, true);
    file_put_contents(
        $themeViewDir . '/post.php',
        '<html><body><h1><?= htmlspecialchars($p->title) ?></h1><div><?= $p->content ?></div></body></html>'
    );
    $this->site->paths->themeDir = 'theme';

    $this->pages = new Pages();
    $this->publisher = new PagePublisher($this->site, $this->pages);
    SiteBuilder::$manifest = [];
});

afterEach(function () {
    FileUtils::recursiveRmdir($this->tempDir);
});

it('publishes HTML and ActivityPub JSON representations', function () {
    $page = Page::fromArray([
        'title' => 'Hello World',
        'slug' => 'hello-world/',
        'content' => '<p>Hello world body</p>',
        'layout' => 'post',
        'lang' => 'en',
        'date' => time(),
        'kind' => 'article',
    ]);
    $this->pages->add($page);

    $this->publisher->publishHtml($page);

    $htmlFile = $this->tempDir . '/public_html/hello-world/index.html';
    $jsonFile = $this->tempDir . '/public_html/hello-world/index.json';

    expect(file_exists($htmlFile))->toBeTrue();
    expect(file_get_contents($htmlFile))->toContain('Hello World');
    expect(file_get_contents($htmlFile))->toContain('Hello world body');

    expect(file_exists($jsonFile))->toBeTrue();
    $json = json_decode(file_get_contents($jsonFile), true);
    expect($json)->toBeArray();
    expect($json['type'])->toBe('Article');

    expect(isset(SiteBuilder::$manifest[$htmlFile]))->toBeTrue();
});

it('skips publishing when page is marked as draft', function () {
    $draftPage = Page::fromArray([
        'title' => 'Draft Post',
        'slug' => 'draft-post/',
        'content' => '<p>Draft body</p>',
        'tags' => ['draft'],
        'layout' => 'post',
        'lang' => 'en',
        'date' => time(),
    ]);

    $this->publisher->publish($draftPage);

    $htmlFile = $this->tempDir . '/public_html/draft-post/index.html';
    $geminiFile = $this->tempDir . '/public_gemini/draft-post/index.gmi';
    $gopherFile = $this->tempDir . '/public_gopher/draft-post/gophermap';

    expect(file_exists($htmlFile))->toBeFalse();
    expect(file_exists($geminiFile))->toBeFalse();
    expect(file_exists($gopherFile))->toBeFalse();
});

it('publishes Gemini gemtext file', function () {
    $page = Page::fromArray([
        'title' => 'Gemini Post',
        'slug' => 'gemini-post/',
        'content' => '<p>This is gemini content</p>',
        'rawBody' => "This is gemini content\n\n- item 1\n- item 2",
        'lang' => 'en',
        'date' => time(),
    ]);

    $this->publisher->publishGemini($page);

    $geminiFile = $this->tempDir . '/public_gemini/gemini-post/index.gmi';
    expect(file_exists($geminiFile))->toBeTrue();

    $content = file_get_contents($geminiFile);
    expect($content)->toContain('# Gemini Post');
    expect($content)->toContain('Published:');
    expect($content)->toContain('by Test Author');
    expect($content)->toContain('=> / Back to Home');
    expect(isset(SiteBuilder::$manifest[$geminiFile]))->toBeTrue();
});

it('publishes Gopher gophermap file', function () {
    $page = Page::fromArray([
        'title' => 'Gopher Post',
        'slug' => 'gopher-post/',
        'content' => '<p>This is gopher content</p>',
        'rawBody' => "This is gopher content",
        'lang' => 'en',
        'date' => time(),
    ]);

    $this->publisher->publishGopher($page);

    $gopherFile = $this->tempDir . '/public_gopher/gopher-post/gophermap';
    expect(file_exists($gopherFile))->toBeTrue();

    $content = file_get_contents($gopherFile);
    expect($content)->toContain('=== Gopher Post ===');
    expect($content)->toContain('1Back to Home');
    expect(isset(SiteBuilder::$manifest[$gopherFile]))->toBeTrue();
});

it('publishes all formats simultaneously with publish() and publishAll()', function () {
    $page1 = Page::fromArray([
        'title' => 'First Post',
        'slug' => 'first-post/',
        'content' => '<p>First</p>',
        'rawBody' => 'First',
        'layout' => 'post',
        'lang' => 'en',
        'date' => time(),
    ]);

    $page2 = Page::fromArray([
        'title' => 'Second Post',
        'slug' => 'second-post/',
        'content' => '<p>Second</p>',
        'rawBody' => 'Second',
        'layout' => 'post',
        'lang' => 'en',
        'date' => time(),
    ]);

    $this->publisher->publishAll([$page1, $page2]);

    expect(file_exists($this->tempDir . '/public_html/first-post/index.html'))->toBeTrue();
    expect(file_exists($this->tempDir . '/public_gemini/first-post/index.gmi'))->toBeTrue();
    expect(file_exists($this->tempDir . '/public_gopher/first-post/gophermap'))->toBeTrue();

    expect(file_exists($this->tempDir . '/public_html/second-post/index.html'))->toBeTrue();
    expect(file_exists($this->tempDir . '/public_gemini/second-post/index.gmi'))->toBeTrue();
    expect(file_exists($this->tempDir . '/public_gopher/second-post/gophermap'))->toBeTrue();
});

it('calculates language links with proper fallbacks', function () {
    $page = Page::fromArray([
        'title' => 'About',
        'slug' => 'about/',
        'kind' => 'page',
        'nick' => 'about',
        'lang' => 'en',
    ]);
    $this->pages->add($page);

    $links = $this->publisher->getLanguageLinks($page);
    expect($links)->toBeArray();
    expect($links['en'])->toBe('/about/');
    // Since 'pt' translation is not present, it should fall back to '/pt/'
    expect($links['pt'])->toBe('/pt/');
});

it('calculates menu links categorized into header and footer', function () {
    $headerPage = Page::fromArray([
        'title' => 'About Page',
        'slug' => 'about/',
        'kind' => 'page',
        'menu' => 'header',
        'menu_order' => 1,
        'lang' => 'en',
    ]);

    $footerPage = Page::fromArray([
        'title' => 'Privacy Policy',
        'slug' => 'privacy/',
        'kind' => 'page',
        'menu' => 'footer',
        'menu_order' => 2,
        'lang' => 'en',
    ]);

    $this->pages->add($headerPage);
    $this->pages->add($footerPage);

    $menu = $this->publisher->getMenuLinks($headerPage);
    expect($menu)->toHaveKeys(['header', 'footer']);

    $headerLabels = array_column($menu['header'], 'label');
    $footerLabels = array_column($menu['footer'], 'label');

    expect($headerLabels)->toContain('About Page');
    expect($footerLabels)->toContain('Privacy Policy');
});

it('binds Pages, Page and Site into Container during publishHtml', function () {
    $themeViewDir = $this->tempDir . '/theme/views';
    file_put_contents(
        $themeViewDir . '/container_check.php',
        '<?php
        $container = \Indieinabox\Core\Container::getInstance();
        echo "pages:" . ($container->has(\Indieinabox\Page\Pages::class) ? "yes" : "no");
        echo " page:" . ($container->has(\Indieinabox\Page\Page::class) ? "yes" : "no");
        echo " site:" . ($container->has(\Indieinabox\Site\Site::class) ? "yes" : "no");
        ?>'
    );

    $page = Page::fromArray([
        'title' => 'Check Page',
        'slug' => 'check/',
        'layout' => 'container_check',
        'lang' => 'en',
    ]);
    $this->pages->add($page);

    $this->publisher->publishHtml($page);

    $outputFile = $this->tempDir . '/public_html/check/index.html';
    expect(file_exists($outputFile))->toBeTrue();
    $content = file_get_contents($outputFile);
    expect($content)->toContain('pages:yes page:yes site:yes');
});

it('publishes dedicated image viewer pages at {post_url}/image{n}/ with baseline JPEG and CC-BY-NC license', function () {
    $page = Page::fromArray([
        'title' => 'Post com Imagens',
        'slug' => 'photos/vacation/',
        'layout' => 'post',
        'lang' => 'en',
        'content' => '<p>Ver foto</p><figure class="content-media"><a href="/media/sunset.jpg" class="dithered-image-link"><img src="/media/sunset_500.gif" alt="Pôr do sol"></a><figcaption><a href="/media/sunset.jpg" class="dithered-image-link">[ Original image ]</a></figcaption></figure>',
    ]);

    $this->pages->add($page);
    $this->publisher->publishHtml($page);

    $viewerFile = $this->tempDir . '/public_html/photos/vacation/image1/index.html';
    expect(file_exists($viewerFile))->toBeTrue();

    $viewerContent = file_get_contents($viewerFile);
    expect($viewerContent)->toContain('rel="license"')
        ->and($viewerContent)->toContain('https://creativecommons.org/licenses/by-nc/4.0/')
        ->and($viewerContent)->toContain('src="/media/sunset.jpg"')
        ->and($viewerContent)->toContain('href="../"')
        ->and($viewerContent)->toContain('Back to post')
        ->and($viewerContent)->not->toContain('download');
});
