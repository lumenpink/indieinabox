<?php

declare(strict_types=1);

use Indieinabox\Site;
use Indieinabox\SiteBuilder;
use Indieinabox\Site\Paths;

/**
 * @property string $tempDir
 * @property Site $site
 * @property Paths $paths
 */
beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indieinabox_menu_test_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/content', 0777, true);
    mkdir($this->tempDir . '/resources/views', 0777, true);
    mkdir($this->tempDir . '/resources/static', 0777, true);

    file_put_contents($this->tempDir . '/resources/views/page.php', "<html><body><?= \$p->content ?></body></html>");

    $this->paths = new Paths(
        $this->tempDir,
        'public_html',
        'public_gemini',
        'public_gopher',
        'public_media',
        'content',
        'resources'
    );
    $this->site = new Site(null, $this->paths);
    
    // Remove kinds from config to avoid default home links
    $this->site->config['kinds'] = [];
});

afterEach(function () {
    \Indieinabox\Helper::recursiveRmdir($this->tempDir);
});

test('root md file without publish tag becomes page and uses slugified name', function () {
    file_put_contents($this->tempDir . '/content/now.md', "---\ntitle: Now Page\n---\nContent");
    
    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    $pages = iterator_to_array($builder->getPages(), false);
    
    expect(count($pages))->toBe(1);
    expect($pages[0]->kind)->toBe('page');
    expect($pages[0]->slug)->toBe('now/');
});

test('root md file with slug tag uses the specified slug', function () {
    file_put_contents($this->tempDir . '/content/now.md', "---\ntitle: Now Page\nslug: right-now\n---\nContent");
    
    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    $pages = iterator_to_array($builder->getPages(), false);
    
    expect(count($pages))->toBe(1);
    expect($pages[0]->slug)->toBe('right-now/');
});

test('root md file with publish: false is skipped', function () {
    file_put_contents($this->tempDir . '/content/draft.md', "---\ntitle: Draft\npublish: false\n---\nContent");
    file_put_contents($this->tempDir . '/content/published.md', "---\ntitle: Published\n---\nContent");
    
    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    $pages = iterator_to_array($builder->getPages(), false);
    
    expect(count($pages))->toBe(1);
    expect($pages[0]->slug)->toBe('published/');
});

test('pages appear in menu by default unless menu: hide is set', function () {
    file_put_contents($this->tempDir . '/content/visible.md', "---\ntitle: Visible\n---\nContent");
    file_put_contents($this->tempDir . '/content/hidden.md', "---\ntitle: Hidden\nmenu: hide\n---\nContent");
    
    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    
    $reflection = new \ReflectionClass(SiteBuilder::class);
    $method = $reflection->getMethod('getMenuLinks');
    $method->setAccessible(true);
    
    $pages = iterator_to_array($builder->getPages(), false);
    $dummyPage = $pages[0]; // 'visible'
    
    $links = $method->invoke($builder, $dummyPage)['footer'];
    
    expect(count($links))->toBe(1);
    expect($links[0]['label'])->toBe('Visible');
});

test('menu flag directs pages to header, footer or both', function () {
    file_put_contents($this->tempDir . '/content/header_only.md', "---\ntitle: Head\nmenu: \"header\"\n---\nContent");
    file_put_contents($this->tempDir . '/content/footer_only.md', "---\ntitle: Foot\nmenu: \"footer\"\n---\nContent");
    file_put_contents($this->tempDir . '/content/both.md', "---\ntitle: Both\nmenu: \"both\"\n---\nContent");
    file_put_contents($this->tempDir . '/content/none.md', "---\ntitle: None\nmenu: hide\n---\nContent");
    
    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    
    $reflection = new \ReflectionClass(SiteBuilder::class);
    $method = $reflection->getMethod('getMenuLinks');
    $method->setAccessible(true);
    
    $pages = iterator_to_array($builder->getPages(), false);
    
    $links = $method->invoke($builder, $pages[0]);
    $headerLinks = $links['header'];
    $footerLinks = $links['footer'];
    
    expect(count($headerLinks))->toBe(2); // Both, Head
    expect(count($footerLinks))->toBe(2); // Both, Foot
    
    expect($headerLinks[0]['label'])->toBe('Both');
    expect($headerLinks[1]['label'])->toBe('Head');
    
    expect($footerLinks[0]['label'])->toBe('Both');
    expect($footerLinks[1]['label'])->toBe('Foot');
});

test('menu links are ordered by menu_order then alphabetically', function () {
    file_put_contents($this->tempDir . '/content/zeta.md', "---\ntitle: Zeta\n---\nContent");
    file_put_contents($this->tempDir . '/content/alpha.md', "---\ntitle: Alpha\n---\nContent");
    file_put_contents($this->tempDir . '/content/second.md', "---\ntitle: Second\nmenu_order: 2\n---\nContent");
    file_put_contents($this->tempDir . '/content/first.md', "---\ntitle: First\nmenu_order: 1\n---\nContent");
    
    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    
    $reflection = new \ReflectionClass(SiteBuilder::class);
    $method = $reflection->getMethod('getMenuLinks');
    $method->setAccessible(true);
    
    $pages = iterator_to_array($builder->getPages(), false);
    $dummyPage = $pages[0];
    
    $links = $method->invoke($builder, $dummyPage)['footer'];
    
    expect(count($links))->toBe(4);
    
    expect($links[0]['label'])->toBe('First');
    expect($links[1]['label'])->toBe('Second');
    expect($links[2]['label'])->toBe('Alpha');
    expect($links[3]['label'])->toBe('Zeta');
});
