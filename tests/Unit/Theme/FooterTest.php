<?php

declare(strict_types=1);

use Indieinabox\Page\Page;
use Indieinabox\Site\Site;
use Indieinabox\Theme\ThemeData;
use Indieinabox\Theme\ThemeManager;

it('renders footer links with RSS, ATOM, and custom links', function () {
    $page = new Page(null, null, null);
    $page->relpath = '';

    $customLinks = [
        ['url' => 'https://example.com/about', 'label' => 'Sobre'],
        ['url' => 'https://example.com/now', 'label' => 'Agora'],
    ];

    $html = ThemeData::getFooterLinks($page, $customLinks);

    expect($html)->toContain('<nav class="footer-links"');
    expect($html)->toContain('href="https://example.com/about">Sobre</a>');
    expect($html)->toContain('href="https://example.com/now">Agora</a>');
    expect($html)->toContain('href="rss.xml">RSS</a>');
    expect($html)->toContain('href="atom.xml">ATOM</a>');
});

it('renders complete footer view with copyright and credits', function () {
    $site = new Site();
    $site->metadata->sitename = 'Lumen Blog';
    $site->metadata->author = 'Lumen Pink';
    $site->metadata->fqdn = 'https://lumen.pink';
    $site->paths->themeDir = dirname(__DIR__, 3) . '/resources';

    \Indieinabox\Core\Container::getInstance()->instance(Site::class, $site);
    $GLOBALS['site'] = $site;

    $page = new Page(null, null, null);
    $page->relpath = '';
    $page->lang = 'pt';

    ob_start();
    ThemeManager::includeView('includes/footer.php', [
        'page' => $page,
        'site' => $site,
        'footerLinks' => []
    ]);
    $output = ob_get_clean();

    expect($output)->toContain('<footer role="contentinfo">');
    expect($output)->toContain('Lumen Blog');
    expect($output)->toContain('Lumen Pink');
    expect($output)->toContain('Publicado com');
    expect($output)->toContain('IndieInABox');
});
