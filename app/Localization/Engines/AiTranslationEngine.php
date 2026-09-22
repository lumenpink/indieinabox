<?php

declare(strict_types=1);

namespace Indieinabox\Localization\Engines;

use Indieinabox\Localization\Contracts\AiTranslationProviderInterface;
use Indieinabox\Localization\Contracts\TranslationEngineInterface;
use Indieinabox\Localization\Services\TranslationCacheService;

/**
 * Intelligent AI & Machine Translation Engine.
 *
 * Combines pluggable AI/LLM providers (Gemini, OpenAI, Claude, Grok, Ollama)
 * with robust SHA-256 caching and tag-masking protection for code and Markdown formatting.
 */
class AiTranslationEngine implements TranslationEngineInterface
{
    private AiTranslationProviderInterface $provider;
    private TranslationCacheService $cache;
    private ?TranslationEngineInterface $fallbackEngine;

    public function __construct(
        AiTranslationProviderInterface $provider,
        ?TranslationCacheService $cache = null,
        ?TranslationEngineInterface $fallbackEngine = null
    ) {
        $this->provider = $provider;
        $this->cache = $cache ?? new TranslationCacheService();
        $this->fallbackEngine = $fallbackEngine ?? new DictionaryTranslationEngine();
    }

    #[\Override]
    public function getName(): string
    {
        return 'ai_' . $this->provider->getName();
    }

    public function getProvider(): AiTranslationProviderInterface
    {
        return $this->provider;
    }

    #[\Override]
    public function translateText(string $text, string $targetLang, string $sourceLang = 'en'): string
    {
        if (trim($text) === '') {
            return $text;
        }

        if (!$this->provider->isAvailable()) {
            return $this->fallbackEngine ? $this->fallbackEngine->translateText($text, $targetLang, $sourceLang) : $text;
        }

        $cacheKey = $this->cache->makeKey($text, $targetLang, $sourceLang, $this->provider->getName(), 'text');
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $translated = $this->provider->translate($text, $targetLang, $sourceLang);
        if ($translated !== $text && $translated !== '') {
            $this->cache->put($cacheKey, $translated);
            return $translated;
        }

        return $this->fallbackEngine ? $this->fallbackEngine->translateText($text, $targetLang, $sourceLang) : $text;
    }

    #[\Override]
    public function translateContent(string $content, string $targetLang, string $sourceLang = 'en'): string
    {
        if (trim($content) === '') {
            return $content;
        }

        if (!$this->provider->isAvailable()) {
            return $this->fallbackEngine ? $this->fallbackEngine->translateContent($content, $targetLang, $sourceLang) : $content;
        }

        $cacheKey = $this->cache->makeKey($content, $targetLang, $sourceLang, $this->provider->getName(), 'content');
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Delegate to AI provider with prompt engineering
        $translated = $this->provider->translate($content, $targetLang, $sourceLang);
        if ($translated !== $content && $translated !== '') {
            $this->cache->put($cacheKey, $translated);
            return $translated;
        }

        return $this->fallbackEngine ? $this->fallbackEngine->translateContent($content, $targetLang, $sourceLang) : $content;
    }
}
