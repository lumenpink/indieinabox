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
    /** @var \Tests\TestCase $this */
    $this->tempDir = sys_get_temp_dir() . '/indieinabox_menu_multilang_test_' . uniqid();
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
    
    // We configure a multi-language setup. 
    // The order is mandatory, default is first ('en')
    $this->site = new Site(null, $this->paths);
    $this->site->localization->lang = ['en', 'pt', 'es'];
    $this->site->localization->defaultLang = 'en';
    
    // Remove kinds from config to avoid default home links
    $this->site->config['kinds'] = [];
});

afterEach(function () {
    /** @var \Tests\TestCase $this */
    \Indieinabox\Helper::recursiveRmdir($this->tempDir);
});

test('root md file in non-default language directory becomes page and uses slugified name', function () {
    /** @var \Tests\TestCase $this */
    mkdir($this->tempDir . '/content/pt', 0777, true);
    file_put_contents($this->tempDir . '/content/pt/agora.md', "---\ntitle: Agora Page\n---\nContent");
    
    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    $pages = iterator_to_array($builder->getPages(), false);
    
    expect(count($pages))->toBe(1);
    expect($pages[0]->kind)->toBe('page');
    expect($pages[0]->lang)->toBe('pt');
    expect($pages[0]->slug)->toBe('pt/agora/');
});

test('root md file in non-default language directory with slug tag uses the specified slug', function () {
    /** @var \Tests\TestCase $this */
    mkdir($this->tempDir . '/content/es', 0777, true);
    file_put_contents($this->tempDir . '/content/es/ahora.md', "---\ntitle: Ahora Page\nslug: en-este-momento\n---\nContent");
    
    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    $pages = iterator_to_array($builder->getPages(), false);
    
    expect(count($pages))->toBe(1);
    expect($pages[0]->slug)->toBe('es/en-este-momento/');
});

test('pages in non-default language appear in the localized menu by default', function () {
    /** @var \Tests\TestCase $this */
    mkdir($this->tempDir . '/content/pt', 0777, true);
    file_put_contents($this->tempDir . '/content/pt/visivel.md', "---\ntitle: Visivel\n---\nContent");
    file_put_contents($this->tempDir . '/content/pt/oculto.md', "---\ntitle: Oculto\nmenu: hide\n---\nContent");
    // Add an English page to ensure menus don't mix languages
    file_put_contents($this->tempDir . '/content/visible-en.md', "---\ntitle: Visible EN\n---\nContent");
    
    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    
    $reflection = new \ReflectionClass(SiteBuilder::class);
    $method = $reflection->getMethod('getMenuLinks');
    $method->setAccessible(true);
    
    $pages = iterator_to_array($builder->getPages(), false);
    
    // Find the Portuguese page to generate its footer links
    $ptPage = null;
    foreach ($pages as $p) {
        if ($p->lang === 'pt' && $p->slug === 'pt/visivel/') {
            $ptPage = $p;
            break;
        }
    }
    
    expect($ptPage)->not->toBeNull();
    
    $links = $method->invoke($builder, $ptPage)['footer'];
    
    expect(count($links))->toBe(1);
    expect($links[0]['label'])->toBe('Visivel');
    expect($links[0]['url'])->toBe('../../pt/visivel/'); // Assuming relpath is '../../' because final slug is pt/visivel/
});

test('menu links for non-default language are ordered by menu_order then alphabetically', function () {
    /** @var \Tests\TestCase $this */
    mkdir($this->tempDir . '/content/pt', 0777, true);
    file_put_contents($this->tempDir . '/content/pt/zeta.md', "---\ntitle: Zeta\n---\nContent");
    file_put_contents($this->tempDir . '/content/pt/alpha.md', "---\ntitle: Alpha\n---\nContent");
    file_put_contents($this->tempDir . '/content/pt/second.md', "---\ntitle: Second\nmenu_order: 2\n---\nContent");
    file_put_contents($this->tempDir . '/content/pt/first.md', "---\ntitle: First\nmenu_order: 1\n---\nContent");
    
    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    
    $reflection = new \ReflectionClass(SiteBuilder::class);
    $method = $reflection->getMethod('getMenuLinks');
    $method->setAccessible(true);
    
    $pages = iterator_to_array($builder->getPages(), false);
    
    $ptPage = null;
    foreach ($pages as $p) {
        if ($p->lang === 'pt') {
            $ptPage = $p;
            break;
        }
    }
    
    $links = $method->invoke($builder, $ptPage)['footer'];
    
    expect(count($links))->toBe(4);
    
    expect($links[0]['label'])->toBe('First');
    expect($links[1]['label'])->toBe('Second');
    expect($links[2]['label'])->toBe('Alpha');
    expect($links[3]['label'])->toBe('Zeta');
});

test('pages in secondary language are virtualized back to default language', function () {
    /** @var \Tests\TestCase $this */
    mkdir($this->tempDir . '/content/pt', 0777, true);
    file_put_contents($this->tempDir . '/content/pt/somente-pt.md', "---\ntitle: Somente PT\n---\nEste post so existe em portugues.");
    
    $builder = new SiteBuilder($this->site);
    $builder->scan($this->tempDir . '/content');
    
    $reflection = new \ReflectionClass(SiteBuilder::class);
    $method = $reflection->getMethod('virtualizeMissingLanguages');
    $method->setAccessible(true);
    $method->invoke($builder);
    
    $pages = iterator_to_array($builder->getPages(), false);
    
    // Total pages should be 3 (one for 'pt', one virtualized for 'en', one virtualized for 'es')
    expect(count($pages))->toBe(3);
    
    $enPage = null;
    $esPage = null;
    foreach ($pages as $p) {
        if ($p->lang === 'en') $enPage = $p;
        if ($p->lang === 'es') $esPage = $p;
    }
    
    expect($enPage)->not->toBeNull();
    expect($enPage->title)->toBe('[EN] Somente PT');
    expect($enPage->slug)->toBe('somente-pt/'); // In English (default), slug has no prefix
    
    expect($esPage)->not->toBeNull();
    expect($esPage->title)->toBe('[ES] Somente PT');
    expect($esPage->slug)->toBe('es/somente-pt/');
});

test('pseudoTranslate mocks the translation format correctly', function () {
    /** @var \Tests\TestCase $this */
    $builder = new SiteBuilder($this->site);
    $page = \Indieinabox\Page::fromArray([
        'title' => 'Original Title',
        'kind' => 'article'
    ]);
    $page->content = new \Indieinabox\Page\Content('Original content');
    $page->content->rawBody = 'Original content';
    
    $builder->pseudoTranslate($page, 'es');
    
    expect($page->title)->toBe('[ES] Original Title');
    expect($page->content->content)->toBe('Original content'); // Did not change body because it has a title
    
    // Test without title (e.g. note)
    $pageNote = \Indieinabox\Page::fromArray([
        'kind' => 'note'
    ]);
    $pageNote->content = new \Indieinabox\Page\Content('Original body');
    $pageNote->content->rawBody = 'Original body';
    
    $builder->pseudoTranslate($pageNote, 'pt');
    
    expect($pageNote->title)->toBe('Untitled');
    expect($pageNote->content->content)->toBe('[PT] Original body');
});
