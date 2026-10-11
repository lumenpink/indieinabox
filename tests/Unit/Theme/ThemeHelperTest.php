<?php

declare(strict_types=1);

use Indieinabox\Page\Page;
use Indieinabox\Theme\ThemeHelper;

it('renders metadata html safely when tags or flowerbed contain arrays or empty items', function () {
    $page = Page::fromArray([
        'title' => 'Test Page',
        'slug' => 'test-page',
        'kind' => 'garden',
        'date' => '2026-10-04 12:00:00',
        'tags' => ['valid', [], ''],
        'flowerbed' => ['general', []],
    ]);
    $page->localizeddate = 'Sunday, October 4 of 2026, 12:00 UTC';

    $html = ThemeHelper::getMetadataHtml($page);

    expect($html)->toContain('#valid')
        ->and($html)->not->toContain('#Array');
});

it('renders post snippets with clickable thumbnail and text linking to post slug', function () {
    $site = new \Indieinabox\Site\Site();
    $site->config['kinds'] = [
        'note' => ['has_title' => false, 'display_mode' => 'thumbnail_snippet']
    ];
    \Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Site\Site::class, $site);
    $GLOBALS['site'] = $site;

    $contextPage = new Page(null, null, null);
    $contextPage->relpath = '../../';

    $post = new Page(null, null, null);
    $post->kind = 'note';
    $post->slug = 'notes/short-thought.html';
    $post->isodate = '2026-10-08T12:00:00Z';
    $post->localizeddate = '2026-10-08';
    $post->content = '<p><img src="/media/pic.gif" alt="Pic"></p><p>A brief thought in the wind.</p>';

    $html = ThemeHelper::renderPostSnippet($contextPage, $post);

    expect($html)->toContain('href="../../notes/short-thought.html"')
        ->and($html)->toContain('img src="/media/pic_thumb.gif"')
        ->and($html)->toContain('A brief thought in the wind');
});

it('renders summary view with clickable thumbnail and clickable text snippet for short notes', function () {
    $site = new \Indieinabox\Site\Site();
    $site->metadata->sitename = 'Lumen Blog';
    $site->localization->defaultLang = 'pt';
    $site->paths->themeDir = dirname(__DIR__, 3) . '/resources';
    $site->config['kinds'] = [
        'note' => ['has_title' => false]
    ];
    \Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Site\Site::class, $site);
    $GLOBALS['site'] = $site;

    $page = new Page(null, null, null);
    $page->kind = 'note';
    $page->slug = 'notes/poem.html';
    $page->relpath = '';
    $page->date = new DateTime('2026-10-08 12:00:00');
    $page->localizeddate = '2026-10-08';
    $page->content = '<p><img src="/media/art.gif" alt="Art"></p><p>Depois da raiva vem a acao.</p>';

    ob_start();
    \Indieinabox\Theme\ThemeManager::includeView('includes/summary.php', [
        'page' => $page,
        'site' => $site,
    ]);
    $output = ob_get_clean();

    // Check that thumbnail is wrapped in link to post
    expect($output)->toContain('<a href="notes/poem.html" class="u-url" style="float:left;')
        ->and($output)->toContain('<img src="/media/art_thumb.gif"')
        // Check that snippet text is wrapped in link to post
        ->and($output)->toContain('<a href="notes/poem.html" class="u-url"')
        ->and($output)->toContain('Depois da raiva vem a acao.</a>');
});

it('hides zero social counters in getMetadataHtml and displays only permalink when no interactions exist', function () {
    $site = new \Indieinabox\Site\Site();
    $site->config['kinds'] = ['note' => ['has_title' => false]];
    \Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Site\Site::class, $site);
    $GLOBALS['site'] = $site;

    $page = new Page(null, null, null);
    $page->kind = 'note';
    $page->slug = 'notes/test.html';
    $page->relpath = '';
    $page->isodate = '2026-10-11T12:00:00Z';
    $page->localizeddate = '2026-10-11';
    $page->shortlink = 'https://lumen.pink/p/abc';
    $page->interactions = [];

    $html = ThemeHelper::getMetadataHtml($page);

    expect($html)->toContain('<a href="https://lumen.pink/p/abc"')
        ->and($html)->not->toContain('0 Like')
        ->and($html)->not->toContain('0 Repost')
        ->and($html)->not->toContain('0 Reply')
        ->and($html)->not->toContain('🔗</a> - ');
});

it('displays only non-zero social counters when interactions exist in getMetadataHtml', function () {
    $site = new \Indieinabox\Site\Site();
    $site->config['kinds'] = ['note' => ['has_title' => false]];
    \Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Site\Site::class, $site);
    $GLOBALS['site'] = $site;

    $page = new Page(null, null, null);
    $page->kind = 'note';
    $page->slug = 'notes/test.html';
    $page->relpath = '';
    $page->isodate = '2026-10-11T12:00:00Z';
    $page->localizeddate = '2026-10-11';
    $page->shortlink = 'https://lumen.pink/p/abc';
    $page->interactions = [
        'likes' => [['id' => '1', 'author' => 'Alice']],
        'reposts' => [],
        'replies' => [],
    ];

    $mockRepo = new class implements \Indieinabox\Repositories\Contracts\InteractionRepositoryInterface {
        public function findByPageSlug(string $slug, ?string $type = null): array {
            if ($type === 'like') {
                return [['id' => '1', 'author' => 'Alice']];
            }
            return [];
        }
        public function listByStatus(string $status = 'pending'): array { return []; }
        public function updateStatus(string $id, string $status, string $type = 'pending'): bool { return true; }
        public function save(string $id, array $metadata, string $content = '', string $channel = 'notifications'): bool { return true; }
        public function delete(string $id, string $type = 'pending'): bool { return true; }
    };
    \Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Repositories\Contracts\InteractionRepositoryInterface::class, $mockRepo);

    $html = ThemeHelper::getMetadataHtml($page);

    expect($html)->toContain('<a href="https://lumen.pink/p/abc"')
        ->and($html)->toContain('1 Like')
        ->and($html)->not->toContain('0 Repost')
        ->and($html)->not->toContain('0 Reply')
        ->and($html)->toContain('🔗</a> - <a href="notes/test.html/interactions#likes"');
});

it('hides zero social counters in summary view', function () {
    $site = new \Indieinabox\Site\Site();
    $site->metadata->sitename = 'Lumen Blog';
    $site->localization->defaultLang = 'pt';
    $site->paths->themeDir = dirname(__DIR__, 3) . '/resources';
    $site->config['kinds'] = ['note' => ['has_title' => false]];
    \Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Site\Site::class, $site);
    $GLOBALS['site'] = $site;

    $page = new Page(null, null, null);
    $page->kind = 'note';
    $page->slug = 'notes/summary-test.html';
    $page->relpath = '';
    $page->date = new DateTime('2026-10-11 12:00:00');
    $page->localizeddate = '2026-10-11';
    $page->shortlink = 'https://lumen.pink/p/xyz';
    $page->interactions = [];

    ob_start();
    \Indieinabox\Theme\ThemeManager::includeView('includes/summary.php', [
        'page' => $page,
        'site' => $site,
    ]);
    $output = ob_get_clean();

    expect($output)->toContain('<a href="https://lumen.pink/p/xyz"')
        ->and($output)->not->toContain('0 Like')
        ->and($output)->not->toContain('0 Repost')
        ->and($output)->not->toContain('0 Reply')
        ->and($output)->not->toContain('🔗</a> - ');
});

it('renders non-zero social counters in summary view when interactions exist', function () {
    $site = new \Indieinabox\Site\Site();
    $site->metadata->sitename = 'Lumen Blog';
    $site->localization->defaultLang = 'pt';
    $site->paths->themeDir = dirname(__DIR__, 3) . '/resources';
    $site->config['kinds'] = ['note' => ['has_title' => false]];
    \Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Site\Site::class, $site);
    $GLOBALS['site'] = $site;

    $page = new Page(null, null, null);
    $page->kind = 'note';
    $page->slug = 'notes/summary-test-active.html';
    $page->relpath = '';
    $page->date = new DateTime('2026-10-11 12:00:00');
    $page->localizeddate = '2026-10-11';
    $page->shortlink = 'https://lumen.pink/p/active';

    $mockRepo = new class implements \Indieinabox\Repositories\Contracts\InteractionRepositoryInterface {
        public function findByPageSlug(string $slug, ?string $type = null): array {
            if ($type === 'repost') {
                return [['id' => '2', 'author' => 'Bob']];
            }
            return [];
        }
        public function listByStatus(string $status = 'pending'): array { return []; }
        public function updateStatus(string $id, string $status, string $type = 'pending'): bool { return true; }
        public function save(string $id, array $metadata, string $content = '', string $channel = 'notifications'): bool { return true; }
        public function delete(string $id, string $type = 'pending'): bool { return true; }
    };
    \Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Repositories\Contracts\InteractionRepositoryInterface::class, $mockRepo);

    ob_start();
    \Indieinabox\Theme\ThemeManager::includeView('includes/summary.php', [
        'page' => $page,
        'site' => $site,
    ]);
    $output = ob_get_clean();

    expect($output)->toContain('<a href="https://lumen.pink/p/active"')
        ->and($output)->toContain('1 Repost')
        ->and($output)->not->toContain('0 Like')
        ->and($output)->not->toContain('0 Reply')
        ->and($output)->toContain('🔗</a> -')
        ->and($output)->toContain('href="notes/summary-test-active.html/interactions#reposts"');
});

it('renders clickable thumbnail and accessible link for photo post without title or text in renderPostSnippet', function () {
    $site = new \Indieinabox\Site\Site();
    $site->localization->defaultLang = 'pt';
    $site->config['kinds'] = [
        'photo' => ['has_title' => false, 'display_mode' => 'default']
    ];
    \Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Site\Site::class, $site);
    $GLOBALS['site'] = $site;

    $contextPage = new Page(null, null, null);
    $contextPage->relpath = '../../';

    $post = new Page(null, null, null);
    $post->kind = 'photo';
    $post->slug = 'photos/untitled-pic.html';
    $post->lang = 'pt';
    $post->isodate = '2026-10-11T14:30:00Z';
    $post->localizeddate = '11/10/2026';
    // Post with only an image tag, no text
    $post->content = '<figure class="content-media"><a href="/media/myphoto.jpg" class="dithered-image-link"><img src="/media/myphoto_512.gif" alt="Sem texto"></a></figure>';

    $html = ThemeHelper::renderPostSnippet($contextPage, $post);

    expect($html)->toContain('href="../../photos/untitled-pic.html"')
        ->and($html)->toContain('img src="/media/myphoto_thumb.gif"')
        ->and($html)->toContain('Foto sem título publicada em');
});
