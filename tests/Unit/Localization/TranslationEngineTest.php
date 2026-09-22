<?php

declare(strict_types=1);

use Indieinabox\Localization\Contracts\TranslationEngineInterface;
use Indieinabox\Localization\Engines\DictionaryTranslationEngine;
use Indieinabox\Page\Page;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\TranslationVirtualizer;

it('translates title and body text using DictionaryTranslationEngine', function () {
    $engine = new DictionaryTranslationEngine([
        'pt' => [
            'Hello World' => 'Olá Mundo',
            'Welcome to indieinabox' => 'Bem-vindo ao indieinabox',
            'Read more' => 'Leia mais'
        ]
    ]);

    expect($engine->getName())->toBe('dictionary');
    expect($engine->translateText('Hello World', 'pt'))->toBe('Olá Mundo');
    expect($engine->translateText('Welcome to indieinabox', 'pt'))->toBe('Bem-vindo ao indieinabox');
    // Unknown term falls back to prefix
    expect($engine->translateText('Unknown phrase', 'pt'))->toBe('[PT] Unknown phrase');
});

it('preserves code blocks, inline code, and HTML tags during dictionary translation', function () {
    $engine = new DictionaryTranslationEngine([
        'pt' => [
            'Hello' => 'Olá',
            'world' => 'mundo',
            'code' => 'código'
        ]
    ]);

    $content = <<<MD
# Hello world

Here is some text with `code` and an HTML tag <div class="highlight">Hello</div>.

```php
// Hello world in PHP
echo "code";
```
MD;

    $translated = $engine->translateContent($content, 'pt');

    // Title and body translated
    expect($translated)->toContain('# Olá mundo');
    // Inline code preserved intact
    expect($translated)->toContain('`code`');
    // HTML tag preserved intact
    expect($translated)->toContain('<div class="highlight">Olá</div>');
    // Fenced code block preserved intact
    expect($translated)->toContain("```php\n// Hello world in PHP\necho \"code\";\n```");
});

it('integrates TranslationEngine with TranslationVirtualizer seamlessly', function () {
    $site = new Site();
    $site->localization->defaultLang = 'en';
    $site->localization->lang = ['en', 'pt'];

    $customEngine = new class implements TranslationEngineInterface {
        public function getName(): string { return 'mock'; }
        public function translateText(string $text, string $targetLang, string $sourceLang = 'en'): string {
            return 'Translated: ' . $text;
        }
        public function translateContent(string $content, string $targetLang, string $sourceLang = 'en'): string {
            return 'Translated Content: ' . $content;
        }
    };

    $virtualizer = new TranslationVirtualizer($site, null, null, $customEngine);
    expect($virtualizer->getTranslationEngine()->getName())->toBe('mock');

    $page = new Page();
    $page->kind = 'articles';
    $page->title = 'Original Article';
    $page->content->content = '<p>Original content</p>';
    $page->content->rawBody = '<p>Original content</p>';

    $virtualizer->pseudoTranslate($page, 'pt');

    expect($page->title)->toBe('Translated: Original Article');
});
