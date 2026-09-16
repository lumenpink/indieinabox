<?php

declare(strict_types=1);

use Indieinabox\Support\FileUtils;
use Indieinabox\Markdown\ContentProcessor;
use Indieinabox\Markdown\FileProcessor;
use Indieinabox\Markdown\LanguageProcessor;
use Indieinabox\Markdown\MarkdownParser;
use Indieinabox\Page\Page;
use Indieinabox\Site\Site;
use Indieinabox\Site\Paths;
use Indieinabox\Translations\UrlTranslations;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_md_parser_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/content', 0777, true);

    $paths = new Paths($this->tempDir);
    $this->site = new Site(null, $paths);
    $this->site->localization->lang = ['en', 'pt', 'es'];
    $this->site->localization->defaultLang = 'en';

    $fileProcessor = new FileProcessor($this->site, $this->tempDir);
    $contentProcessor = new ContentProcessor();
    $urlTranslations = new UrlTranslations([]);
    $languageProcessor = new LanguageProcessor($this->site, $urlTranslations);

    $this->parser = new MarkdownParser(
        $fileProcessor,
        $contentProcessor,
        $languageProcessor,
        $this->site
    );

    global $site;
    $site = $this->site;
    $this->site->config['kinds'] = [
        'article' => [
            'content_dir' => 'articles',
            'folder' => ['en' => 'articles', 'pt' => 'artigos', 'es' => 'articulos']
        ]
    ];
});

afterEach(function () {
    global $site;
    $site = null;
    FileUtils::recursiveRmdir($this->tempDir);
});

it('initializes dependencies and exposes getters', function () {
    expect($this->parser->getFileProcessor())->toBeInstanceOf(FileProcessor::class);
    expect($this->parser->getContentProcessor())->toBeInstanceOf(ContentProcessor::class);
    expect($this->parser->getLanguageProcessor())->toBeInstanceOf(LanguageProcessor::class);
    expect($this->parser->getSite())->toBe($this->site);
});

it('calculates relative path based on slug depth', function () {
    expect($this->parser->calculateRelativePath('/'))->toBe('./');
    expect($this->parser->calculateRelativePath('index.html'))->toBe('./');
    expect($this->parser->calculateRelativePath('about/'))->toBe('../');
    expect($this->parser->calculateRelativePath('notes/2026-09/my-post/'))->toBe('../../../');
});

it('detects language from top-level directory segment', function () {
    // Default language when no locale prefix
    [$lang1, $clean1, $isRoot1] = $this->parser->detectLanguage('articles/post.md', ['en', 'pt', 'es'], 'en');
    expect($lang1)->toBe('en');
    expect($clean1)->toBe('articles/post.md');
    expect($isRoot1)->toBeFalse();

    // Detected language when locale prefix matches
    [$lang2, $clean2, $isRoot2] = $this->parser->detectLanguage('pt/articles/post.md', ['en', 'pt', 'es'], 'en');
    expect($lang2)->toBe('pt');
    expect($clean2)->toBe('articles/post.md');
    expect($isRoot2)->toBeFalse();

    // Root page in secondary locale
    [$lang3, $clean3, $isRoot3] = $this->parser->detectLanguage('es/about.md', ['en', 'pt', 'es'], 'en');
    expect($lang3)->toBe('es');
    expect($clean3)->toBe('about.md');
    expect($isRoot3)->toBeTrue();
});

it('builds canonical slugs respecting prettylinks option', function () {
    $fileInfo = ['filename' => 'my-post', 'ext' => 'md'];

    // Prettylinks enabled (default)
    $slug1 = $this->parser->buildSlug('notes/my-post.md', $fileInfo, [], 'en', 'en');
    expect($slug1)->toBe('notes/my-post/');

    // Prettylinks enabled with secondary language
    $slug2 = $this->parser->buildSlug('notes/my-post.md', $fileInfo, [], 'pt', 'en');
    expect($slug2)->toBe('pt/notes/my-post/');

    // Prettylinks disabled
    $this->site->options->prettylinks = false;
    $slug3 = $this->parser->buildSlug('notes/my-post.md', $fileInfo, [], 'en', 'en');
    expect($slug3)->toBe('notes/my-post.html');
});

it('returns null for non-markdown or unsupported file extensions', function () {
    $invalidFile = $this->tempDir . '/content/script.php';
    file_put_contents($invalidFile, '<?php echo "bad";');

    $result = $this->parser->parse($invalidFile);
    expect($result)->toBeNull();
});

it('returns null when post has publish set to false', function () {
    $draftFile = $this->tempDir . '/content/draft.md';
    file_put_contents($draftFile, "---\ntitle: Draft Post\npublish: false\n---\nDraft content.");

    $result = $this->parser->parse($draftFile);
    expect($result)->toBeNull();
});

it('parses valid markdown file into populated Page object', function () {
    mkdir($this->tempDir . '/content/articles', 0777, true);
    $postFile = $this->tempDir . '/content/articles/welcome.md';
    file_put_contents($postFile, "---\ntitle: Welcome\nkind: article\ntags:\n  - news\n  - tech\n---\nWelcome to my site.");

    $page = $this->parser->parse($postFile);

    expect($page)->toBeInstanceOf(Page::class);
    expect($page->title)->toBe('Welcome');
    expect($page->kind)->toBe('article');
    expect($page->tags)->toContain('news')->and($page->tags)->toContain('tech');
    expect($page->rawBody)->toBe('Welcome to my site.');
    expect($page->slug)->toBe('articles/welcome/');
    expect($page->relpath)->toBe('../../');
});
