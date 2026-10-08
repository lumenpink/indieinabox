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
