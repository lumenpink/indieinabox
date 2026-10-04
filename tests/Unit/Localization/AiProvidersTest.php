<?php

declare(strict_types=1);

use Indieinabox\Localization\Engines\AiTranslationEngine;
use Indieinabox\Localization\Engines\DictionaryTranslationEngine;
use Indieinabox\Localization\Providers\ClaudeProvider;
use Indieinabox\Localization\Providers\GeminiProvider;
use Indieinabox\Localization\Providers\OpenAiCompatibleProvider;
use Indieinabox\Localization\Services\TranslationCacheService;
use Indieinabox\Page\Page;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\TranslationVirtualizer;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_ai_test_' . uniqid();
    mkdir($this->tempDir);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        exec("rm -rf " . escapeshellarg($this->tempDir));
    }
});

it('translates text using GeminiProvider with mock transport', function () {
    $transport = function (string $url, array $headers, string $body) {
        expect($url)->toContain('generativelanguage.googleapis.com');
        expect($url)->toContain('gemini-1.5-flash');
        $data = json_decode($body, true);
        expect($data['contents'][0]['parts'][0]['text'])->toBe('Hello world');

        return [
            'code' => 200,
            'body' => json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Olá mundo']
                            ]
                        ]
                    ]
                ]
            ])
        ];
    };

    $provider = new GeminiProvider('fake-gemini-key', 'gemini-1.5-flash', $transport);
    expect($provider->getName())->toBe('gemini');
    expect($provider->isAvailable())->toBeTrue();

    $translated = $provider->translate('Hello world', 'pt', 'en');
    expect($translated)->toBe('Olá mundo');
});

it('translates text using OpenAiCompatibleProvider for OpenAI, Grok, and Ollama', function () {
    $transport = function (string $url, array $headers, string $body) {
        $headersStr = implode("\n", $headers);
        expect($headersStr)->toContain('Authorization: Bearer fake-openai-key');
        expect($url)->toBe('https://api.openai.com/v1/chat/completions');

        return [
            'code' => 200,
            'body' => json_encode([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Bonjour le monde'
                        ]
                    ]
                ]
            ])
        ];
    };

    $provider = new OpenAiCompatibleProvider(
        'openai',
        'OpenAI GPT',
        'https://api.openai.com/v1/chat/completions',
        'fake-openai-key',
        'gpt-4o-mini',
        $transport
    );

    expect($provider->getName())->toBe('openai');
    expect($provider->isAvailable())->toBeTrue();
    expect($provider->translate('Hello world', 'fr', 'en'))->toBe('Bonjour le monde');

    // Test Ollama local without requiring API key
    $ollama = new OpenAiCompatibleProvider('ollama', 'Ollama (Local)', 'http://localhost:11434/v1/chat/completions', '');
    expect($ollama->isAvailable())->toBeTrue();
});

it('translates text using ClaudeProvider with mock transport', function () {
    $transport = function (string $url, array $headers, string $body) {
        $headersStr = implode("\n", $headers);
        expect($headersStr)->toContain('x-api-key: fake-claude-key');
        expect($url)->toBe('https://api.anthropic.com/v1/messages');

        return [
            'code' => 200,
            'body' => json_encode([
                'content' => [
                    [
                        'text' => 'Hallo Welt'
                    ]
                ]
            ])
        ];
    };

    $provider = new ClaudeProvider('fake-claude-key', 'claude-3-5-haiku-20241022', $transport);
    expect($provider->getName())->toBe('claude');
    expect($provider->isAvailable())->toBeTrue();
    expect($provider->translate('Hello world', 'de', 'en'))->toBe('Hallo Welt');
});

it('caches translations via TranslationCacheService to prevent redundant API requests', function () {
    $callCount = 0;
    $transport = function () use (&$callCount) {
        $callCount++;
        return [
            'code' => 200,
            'body' => json_encode([
                'choices' => [
                    [
                        'message' => ['content' => 'Traduzido']
                    ]
                ]
            ])
        ];
    };

    $provider = new OpenAiCompatibleProvider('openai', 'OpenAI', 'https://api.openai.com', 'key', 'gpt', $transport);
    $cacheService = new TranslationCacheService($this->tempDir . '/cache');
    $engine = new AiTranslationEngine($provider, $cacheService);

    // 1st call triggers transport
    $first = $engine->translateContent('Original article content', 'pt', 'en');
    expect($first)->toBe('Traduzido');
    expect($callCount)->toBe(1);

    // 2nd call hits cache and does NOT call transport
    $second = $engine->translateContent('Original article content', 'pt', 'en');
    expect($second)->toBe('Traduzido');
    expect($callCount)->toBe(1);
});

it('virtualizes pages with translated_by: ai metadata flag when AI engine is active', function () {
    $site = new Site();
    $site->localization->defaultLang = 'en';
    $site->localization->lang = ['en', 'pt'];

    $provider = new OpenAiCompatibleProvider('openai', 'OpenAI', 'https://api.openai.com', 'key', 'gpt', function () {
        return [
            'code' => 200,
            'body' => json_encode(['choices' => [['message' => ['content' => 'Conteúdo em Português']]]])
        ];
    });

    $engine = new AiTranslationEngine($provider, new TranslationCacheService($this->tempDir . '/cache'));
    $virtualizer = new TranslationVirtualizer($site, null, null, $engine);

    $page = new Page();
    $page->kind = 'articles';
    $page->slug = 'articles/my-first-post/';
    $page->title = 'My First Post';
    $page->content->content = '<p>English post content</p>';
    $page->content->rawBody = '<p>English post content</p>';

    $pages = new \Indieinabox\Page\Pages();
    $pages->add($page);

    $virtualizer->virtualize($pages);

    // Verify virtualized Portuguese page was created
    expect(count($pages))->toBe(2);
    $ptPage = null;
    foreach ($pages as $p) {
        if ($p->lang === 'pt') {
            $ptPage = $p;
            break;
        }
    }

    expect($ptPage)->not->toBeNull();
    expect($ptPage->metadata->translated_by)->toBe('ai');
    expect($ptPage->metadata->translation_source_lang)->toBe('en');
    expect($ptPage->content->content)->toBe('Conteúdo em Português');
});

it('GeminiProvider configures permissive safetySettings and payload for academic/philosophical content (#58)', function () {
    $recordedPayload = null;
    $transport = function (string $url, array $headers, string $body) use (&$recordedPayload) {
        $recordedPayload = json_decode($body, true);
        return [
            'code' => 200,
            'body' => json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Translated long text']
                            ]
                        ]
                    ]
                ]
            ])
        ];
    };

    $provider = new GeminiProvider('fake-key', 'gemini-3.5-flash', $transport);
    $result = $provider->translate('Long article about travesti identity', 'en', 'pt-BR');

    expect($result)->toBe('Translated long text');
    expect($recordedPayload)->toHaveKey('safetySettings');
    expect($recordedPayload['safetySettings'])->toBeArray();

    $categories = array_column($recordedPayload['safetySettings'], 'category');
    expect($categories)->toContain('HARM_CATEGORY_HARASSMENT')
        ->toContain('HARM_CATEGORY_HATE_SPEECH')
        ->toContain('HARM_CATEGORY_SEXUALLY_EXPLICIT')
        ->toContain('HARM_CATEGORY_DANGEROUS_CONTENT');

    foreach ($recordedPayload['safetySettings'] as $setting) {
        expect($setting['threshold'])->toBe('BLOCK_NONE');
    }
});

it('GeminiProvider has a default timeout of at least 180s for long article translation (#58)', function () {
    $provider = new GeminiProvider('fake-key', 'gemini-3.5-flash');
    expect($provider->getTimeout())->toBeGreaterThanOrEqual(180);
});

