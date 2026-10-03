<?php

declare(strict_types=1);

use Indieinabox\Localization\AiModels;
use Indieinabox\Localization\Engines\AiTranslationEngine;
use Indieinabox\Localization\Providers\ClaudeProvider;
use Indieinabox\Localization\Providers\GeminiProvider;
use Indieinabox\Localization\Providers\OpenAiCompatibleProvider;
use Indieinabox\Localization\Services\TranslationCacheService;

it('catalogues recommended and available AI models correctly', function () {
    $catalog = AiModels::getCatalog();
    expect($catalog)->toHaveKeys(['gemini', 'claude', 'openai', 'grok', 'ollama']);

    // Check Gemini defaults and recommendations
    expect(AiModels::getDefaultModel('gemini'))->toBe('gemini-3.5-flash');
    expect(AiModels::isRecommended('gemini', 'gemini-3.5-flash'))->toBeTrue();
    expect(AiModels::isRecommended('gemini', 'gemini-3.5-flash-lite'))->toBeFalse();
    expect(AiModels::isRecommended('gemini', 'gemini-2.5-flash'))->toBeFalse();

    // Check Claude defaults
    expect(AiModels::getDefaultModel('claude'))->toBe('claude-3-5-haiku-20241022');
    expect(AiModels::isRecommended('claude', 'claude-3-5-haiku-20241022'))->toBeTrue();

    // Check OpenAI defaults
    expect(AiModels::getDefaultModel('openai'))->toBe('gpt-4o-mini');
    expect(AiModels::isRecommended('openai', 'gpt-4o-mini'))->toBeTrue();

    // Check Ollama defaults
    expect(AiModels::getDefaultModel('ollama'))->toBe('llama3.2');
});

it('successfully translates text via GeminiProvider using mock transport', function () {
    $capturedUrl = null;
    $capturedPayload = null;

    $mockTransport = function (string $url, array $headers, string $payload) use (&$capturedUrl, &$capturedPayload) {
        $capturedUrl = $url;
        $capturedPayload = json_decode($payload, true);

        return [
            'code' => 200,
            'body' => json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Olá mundo! Bem-vindo ao ciberespaço.']
                            ]
                        ]
                    ]
                ]
            ]),
            'error' => ''
        ];
    };

    $provider = new GeminiProvider('fake-api-key', 'gemini-2.5-flash', $mockTransport);
    $result = $provider->translate('Hello world! Welcome to cyberspace.', 'pt', 'en');

    expect($result)->toBe('Olá mundo! Bem-vindo ao ciberespaço.');
    expect($capturedUrl)->toContain('gemini-2.5-flash');
    expect($capturedUrl)->toContain('key=fake-api-key');
    expect($capturedPayload['contents'][0]['parts'][0]['text'])->toBe('Hello world! Welcome to cyberspace.');
});

it('successfully translates text via ClaudeProvider using mock transport', function () {
    $capturedHeaders = null;
    $capturedPayload = null;

    $mockTransport = function (string $url, array $headers, string $payload) use (&$capturedHeaders, &$capturedPayload) {
        $capturedHeaders = $headers;
        $capturedPayload = json_decode($payload, true);

        return [
            'code' => 200,
            'body' => json_encode([
                'content' => [
                    ['text' => 'Texto traduzido pelo Claude com precisão literária.']
                ]
            ]),
            'error' => ''
        ];
    };

    $provider = new ClaudeProvider('claude-secret-key', 'claude-3-5-haiku-20241022', $mockTransport);
    $result = $provider->translate('Text translated by Claude with literary precision.', 'pt', 'en');

    expect($result)->toBe('Texto traduzido pelo Claude com precisão literária.');
    expect($capturedHeaders)->toContain('x-api-key: claude-secret-key');
    expect($capturedPayload['model'])->toBe('claude-3-5-haiku-20241022');
});

it('successfully translates text via OpenAiCompatibleProvider using mock transport', function () {
    $capturedPayload = null;

    $mockTransport = function (string $url, array $headers, string $payload) use (&$capturedPayload) {
        $capturedPayload = json_decode($payload, true);

        return [
            'code' => 200,
            'body' => json_encode([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Conteúdo traduzido com sucesso.'
                        ]
                    ]
                ]
            ]),
            'error' => ''
        ];
    };

    $provider = new OpenAiCompatibleProvider(
        'openai',
        'OpenAI GPT',
        'https://api.openai.com/v1/chat/completions',
        'sk-fake-key',
        'gpt-4o-mini',
        $mockTransport
    );

    $result = $provider->translate('Successfully translated content.', 'pt', 'en');
    expect($result)->toBe('Conteúdo traduzido com sucesso.');
    expect($capturedPayload['model'])->toBe('gpt-4o-mini');
});

it('handles rate limits (HTTP 429) and auth errors gracefully by falling back to original text', function () {
    $failingTransport = function () {
        return [
            'code' => 429,
            'body' => json_encode(['error' => ['message' => 'Rate limit exceeded']]),
            'error' => 'Too Many Requests'
        ];
    };

    $provider = new GeminiProvider('valid-key', 'gemini-2.5-flash', $failingTransport);
    $originalText = 'Original text that should not crash.';
    $result = $provider->translate($originalText, 'pt', 'en');

    expect($result)->toBe($originalText);
});

it('uses TranslationCacheService to prevent redundant AI API calls', function () {
    $tempDir = sys_get_temp_dir() . '/ai_trans_cache_' . uniqid('', true);
    mkdir($tempDir, 0777, true);

    $cache = new TranslationCacheService($tempDir);
    $callCount = 0;

    $mockTransport = function () use (&$callCount) {
        $callCount++;
        return [
            'code' => 200,
            'body' => json_encode([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Tradução memorizada em cache.']
                            ]
                        ]
                    ]
                ]
            ]),
            'error' => ''
        ];
    };

    $provider = new GeminiProvider('key', 'gemini-2.5-flash', $mockTransport);
    $engine = new AiTranslationEngine($provider, $cache);

    $firstResult = $engine->translateContent('Cached translation sample.', 'pt', 'en');
    expect($firstResult)->toBe('Tradução memorizada em cache.');
    expect($callCount)->toBe(1);

    // Second execution must hit disk cache
    $secondResult = $engine->translateContent('Cached translation sample.', 'pt', 'en');
    expect($secondResult)->toBe('Tradução memorizada em cache.');
    expect($callCount)->toBe(1);

    // Cleanup
    array_map('unlink', glob("$tempDir/*.*"));
    @rmdir($tempDir);
});
