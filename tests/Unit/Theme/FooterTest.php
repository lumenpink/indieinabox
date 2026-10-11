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

it('renders canonical 2-line footer in 10 languages according to GOST Profile Lumen', function () {
    $matrix = [
        'pt' => [
            'travesti_link' => 'https://travesti.2lp.in/pt/',
            'phrase' => 'travesti latina',
            'country' => 'Brasil 🇧🇷',
            'by' => 'por',
        ],
        'en' => [
            'travesti_link' => 'https://travesti.2lp.in/en/',
            'phrase' => 'Latin American travesti',
            'country' => 'Brazil 🇧🇷',
            'by' => 'by',
        ],
        'es' => [
            'travesti_link' => 'https://travesti.2lp.in/es/',
            'phrase' => 'travesti latinoamericana',
            'country' => 'Brasil 🇧🇷',
            'by' => 'por',
        ],
        'fr' => [
            'travesti_link' => 'https://travesti.2lp.in/fr/',
            'phrase' => 'travesti latino-américaine',
            'country' => 'Brésil 🇧🇷',
            'by' => 'par',
        ],
        'de' => [
            'travesti_link' => 'https://travesti.2lp.in/de/',
            'phrase' => 'lateinamerikanischen Travesti',
            'country' => 'Brasilien 🇧🇷',
            'by' => 'von',
        ],
        'it' => [
            'travesti_link' => 'https://travesti.2lp.in/it/',
            'phrase' => 'travesti latinoamericana',
            'country' => 'Brasile 🇧🇷',
            'by' => 'da',
        ],
        'ru' => [
            'travesti_link' => 'https://travesti.2lp.in/ru/',
            'phrase' => 'латиноамериканской travesti',
            'country' => 'Бразилия 🇧🇷',
            'by' => 'от',
        ],
        'zh' => [
            'travesti_link' => 'https://travesti.2lp.in/zh/',
            'phrase' => '拉美 travesti',
            'country' => '巴西 🇧🇷',
            'by' => '来自',
        ],
        'ja' => [
            'travesti_link' => 'https://travesti.2lp.in/ja/',
            'phrase' => 'ラテンアメリカの travesti',
            'country' => 'ブラジル 🇧🇷',
            'by' => '制作:',
        ],
        'ar' => [
            'travesti_link' => 'https://travesti.2lp.in/ar/',
            'phrase' => 'travesti أمريكية لاتينية',
            'country' => 'البرازيل 🇧🇷',
            'by' => 'بواسطة',
        ],
    ];

    foreach ($matrix as $lang => $expected) {
        $html = ThemeData::getCanonicalFooter($lang);

        // Linha 1 assertions
        expect($html)->toContain('href="' . $expected['travesti_link'] . '"');
        expect($html)->toContain($expected['phrase']);
        expect($html)->toContain('🏳️‍⚧️ :)');

        // Linha 2 assertions
        expect($html)->toContain('IndieInABox');
        expect($html)->toContain('href="https://lumen.pink">Lumen Pink</a>');
        expect($html)->toContain($expected['country']);
        expect($html)->toContain('Apache-2.0');
        expect($html)->toMatch('/(build est[aá]tico|static build|compilaci[oó]n est[aá]tica|build|compilação):/i');
    }
});

it('renders complete footer view with canonical footer and h-card', function () {
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
    expect($output)->toContain('https://travesti.2lp.in/pt/');
    expect($output)->toContain('travesti latina');
    expect($output)->toContain('🏳️‍⚧️ :)');
    expect($output)->toContain('IndieInABox');
    expect($output)->toContain('https://lumen.pink');
    expect($output)->toContain('Brasil 🇧🇷');
});

it('renders post kinds links in footer only for enabled kinds with published posts', function () {
    $site = new Site();
    $site->metadata->sitename = 'Lumen Blog';
    $site->localization->defaultLang = 'pt';
    $site->options->prettylinks = true;
    $site->config['kinds'] = [
        'article' => ['content_dir' => 'artigos', 'title' => ['pt' => 'Artigos']],
        'photo' => ['content_dir' => 'fotos', 'title' => ['pt' => 'Fotos']],
        'note' => ['content_dir' => 'notas', 'title' => ['pt' => 'Notas']],
        'bookmark' => ['content_dir' => 'favoritos', 'title' => ['pt' => 'Favoritos'], 'show_in_menu' => false],
    ];

    $post1 = new Page(null, null, null);
    $post1->kind = 'photo';
    $post1->slug = 'fotos/1.html';

    $post2 = new Page(null, null, null);
    $post2->kind = 'article';
    $post2->slug = 'artigos/1.html';

    $pages = new \Indieinabox\Page\Pages([$post1, $post2]);

    $currentPage = new Page(null, null, null);
    $currentPage->relpath = '../../';
    $currentPage->lang = 'pt';

    $html = ThemeData::getKindLinks($currentPage, $pages, $site);

    expect($html)->toContain('<nav class="footer-kinds"')
        ->and($html)->toContain('href="../../fotos/"')
        ->and($html)->toContain('Fotos (1)</a>')
        ->and($html)->toContain('href="../../artigos/"')
        ->and($html)->toContain('Artigos (1)</a>')
        ->and($html)->not->toContain('Notas')
        ->and($html)->not->toContain('Favoritos');
});

it('returns empty string from getKindLinks when no pages or posts exist', function () {
    $page = new Page(null, null, null);
    $page->relpath = '';
    $html = ThemeData::getKindLinks($page, new \Indieinabox\Page\Pages([]));
    expect($html)->toBe('');
});

it('renders correct post counts when multiple posts belong to same kind', function () {
    $site = new Site();
    $site->localization->defaultLang = 'pt';
    $site->options->prettylinks = true;
    $site->config['kinds'] = [
        'note' => ['content_dir' => 'notas', 'title' => ['pt' => 'Notas']],
        'photo' => ['content_dir' => 'fotos', 'title' => ['pt' => 'Fotos']],
    ];

    $notes = [];
    for ($i = 0; $i < 15; $i++) {
        $p = new Page(null, null, null);
        $p->kind = 'note';
        $p->slug = "notas/{$i}.html";
        $notes[] = $p;
    }

    $photos = [];
    for ($i = 0; $i < 45; $i++) {
        $p = new Page(null, null, null);
        $p->kind = 'photo';
        $p->slug = "fotos/{$i}.html";
        $photos[] = $p;
    }

    $pages = new \Indieinabox\Page\Pages(array_merge($notes, $photos));

    $currentPage = new Page(null, null, null);
    $currentPage->relpath = '';
    $currentPage->lang = 'pt';

    $html = ThemeData::getKindLinks($currentPage, $pages, $site);

    expect($html)->toContain('Notas (15)</a>')
        ->and($html)->toContain('Fotos (45)</a>');
});
