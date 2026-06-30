<?php

declare(strict_types=1);

use bovigo\vfs\vfsStream;
use Indieinabox\Site;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Support;
use Indieinabox\MarkdownParser;
use Indieinabox\Markdown\FileProcessor;
use Indieinabox\Markdown\ContentProcessor;
use Indieinabox\Markdown\LanguageProcessor;
use Indieinabox\Translations\UrlTranslations;

beforeEach(function () {
    global $site, $base, $parsedown, $urltranslations, $originaldaysofweek, $originalmonths, $intl;
    global $backupSite, $backupBase, $backupParsedown, $backupUrltranslations;

    if (empty($intl)) {
        include __DIR__ . '/../../data/intl.php';
    }

    vfsStream::setup('root', null, [
        'content' => [
            'blog' => [
                'my-post.md' => "---\n"
                    . "title: My Cool Post\n"
                    . "date: 1609459200\n"
                    . "tags:\n"
                    . "  - news\n"
                    . "---\n"
                    . "Hello #world, this is a test. Check out [my link](/blog/other-post).\n"
                    . "Also #anotherTag and #world."
            ]
        ]
    ]);

    $backupSite = $site ?? null;
    $backupBase = $base ?? null;
    $backupParsedown = $parsedown ?? null;
    $backupUrltranslations = $urltranslations ?? null;

    $site = new Site(
        null,
        new Paths('vfs://root', 'public_html', 'public_gemini', 'public_gopher', 'public_media', 'content'),
        null,
        null,
        new Support(['md', 'html'])
    );

    $base = 'vfs://root';
    $urltranslations = [];
});

afterEach(function () {
    global $site, $base, $parsedown, $urltranslations;
    global $backupSite, $backupBase, $backupParsedown, $backupUrltranslations;

    $site = $backupSite;
    $base = $backupBase;
    $parsedown = $backupParsedown;
    $urltranslations = $backupUrltranslations;
});

it('parses markdown file and extracts tags and formats links', function () {
    global $site, $base, $parsedown, $urltranslations;

    $fileProcessor     = new FileProcessor($site, $base);
    $contentProcessor  = new ContentProcessor();
    $urlTranslationsObj   = new UrlTranslations($urltranslations ?? []);
    $languageProcessor = new LanguageProcessor($site, $urlTranslationsObj);

    $parser = new MarkdownParser(
        $fileProcessor,
        $contentProcessor,
        $languageProcessor,
        $site
    );

    $filePath = 'vfs://root/content/blog/my-post.md';
    $page = $parser->parse($filePath);

    expect($page)->toBeInstanceOf(\Indieinabox\Page::class);
    expect($page->title)->toBe('My Cool Post');
    expect($page->slug)->toBe('blog/my-post/');

    expect($page->tags)->toContain('news')
        ->and($page->tags)->toContain('world')
        ->and($page->tags)->toContain('anothertag')
        ->and(count($page->tags))->toBe(3);

    expect((string) $page->content)->toContain('<a href="/blog/other-post/">my link</a>');
});

it('detects language from top-level directory path and sets correct slug/kind', function () {
    global $site, $base, $parsedown, $urltranslations;

    $site->localization->lang = ['en', 'pt', 'es'];
    $site->localization->defaultLang = 'en';
    $site->config['kinds'] = [
        'article' => [
            'content_dir' => 'articles',
            'title' => [
                'en' => 'Articles',
                'pt' => 'Artigos'
            ]
        ]
    ];

    vfsStream::setup('root', null, [
        'content' => [
            'pt' => [
                'articles' => [
                    'my-post.md' => "---\ntitle: Meu Post\n---\nOlá mundo."
                ]
            ]
        ]
    ]);

    $fileProcessor     = new FileProcessor($site, $base);
    $contentProcessor  = new ContentProcessor();
    $urlTranslationsObj   = new UrlTranslations($urltranslations ?? []);
    $languageProcessor = new LanguageProcessor($site, $urlTranslationsObj);

    $parser = new MarkdownParser(
        $fileProcessor,
        $contentProcessor,
        $languageProcessor,
        $site
    );

    $filePath = 'vfs://root/content/pt/articles/my-post.md';
    $page = $parser->parse($filePath);

    expect($page)->toBeInstanceOf(\Indieinabox\Page::class);
    expect($page->lang)->toBe('pt');
    expect($page->slug)->toBe('pt/artigos/my-post/');
    expect($page->kind)->toBe('article');
});

it('virtualizes missing language translations and updates flags links correctly', function () {
    global $site, $base, $parsedown, $urltranslations;

    $site->localization->lang = ['en', 'pt', 'es'];
    $site->localization->defaultLang = 'en';
    $site->config['kinds'] = [
        'article' => [
            'content_dir' => 'articles',
            'title' => [
                'en' => 'Articles',
                'pt' => 'Artigos',
                'es' => 'Articulos'
            ]
        ]
    ];

    vfsStream::setup('root', null, [
        'content' => [
            'articles' => [
                'my-post.md' => "---\ntitle: English Post\n---\nHello World."
            ]
        ]
    ]);

    $builder = new \Indieinabox\SiteBuilder($site);
    $builder->scan('vfs://root/content');
    
    expect($builder->getPages()->count())->toBe(1);
    
    $builder->build();
    
    $pages = $builder->getPages()->all();
    expect(count($pages))->toBe(3);
    
    $ptPage = null;
    $esPage = null;
    $enPage = null;
    foreach ($pages as $p) {
        if ($p->lang === 'pt') {
            $ptPage = $p;
        } elseif ($p->lang === 'es') {
            $esPage = $p;
        } else {
            $enPage = $p;
        }
    }
    
    expect($enPage)->not->toBeNull();
    expect($ptPage)->not->toBeNull();
    expect($esPage)->not->toBeNull();
    
    expect($ptPage->title)->toBe('[PT] English Post');
    expect($esPage->title)->toBe('[ES] English Post');
    
    expect($ptPage->slug)->toBe('pt/artigos/my-post/');
    expect($esPage->slug)->toBe('es/articulos/my-post/');

    $reflection = new \ReflectionClass(\Indieinabox\SiteBuilder::class);
    $method = $reflection->getMethod('getLanguageLinks');
    $method->setAccessible(true);
    
    $links = $method->invoke($builder, $ptPage);
    expect($links)->toBe([
        'en' => '/articles/my-post/',
        'pt' => '/pt/artigos/my-post/',
        'es' => '/es/articulos/my-post/'
    ]);
});

it('virtualizes page translations without titles by prefixing text content', function () {
    global $site, $base;

    $site->localization->lang = ['en', 'pt'];
    $site->localization->defaultLang = 'en';
    $site->config['kinds'] = [
        'note' => [
            'content_dir' => 'notes',
            'has_title' => false,
            'title' => [
                'en' => 'Notes',
                'pt' => 'Notas'
            ]
        ]
    ];

    vfsStream::setup('root', null, [
        'content' => [
            'notes' => [
                'my-note.md' => "---\ntags: [tag1]\n---\nHello this is a note."
            ]
        ]
    ]);

    $builder = new \Indieinabox\SiteBuilder($site);
    $builder->scan('vfs://root/content');
    $builder->build();
    
    $pages = $builder->getPages()->all();
    expect(count($pages))->toBe(2);
    
    $ptNote = null;
    foreach ($pages as $p) {
        if ($p->lang === 'pt') {
            $ptNote = $p;
        }
    }
    
    expect($ptNote)->not->toBeNull();
    expect(str_contains($ptNote->content->content, '[PT] '))->toBeTrue();
    expect(str_contains($ptNote->content->rawBody, '[PT] Hello this is a note.'))->toBeTrue();
});
