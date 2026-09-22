<?php

declare(strict_types=1);

namespace Indieinabox\Localization\Contracts;

/**
 * Interface for AI and Machine Translation provider adapters.
 */
interface AiTranslationProviderInterface
{
    /**
     * Unique identifier for this provider (e.g. 'gemini', 'openai', 'claude', 'grok', 'ollama', 'deepl', 'libretranslate').
     */
    public function getName(): string;

    /**
     * Human-friendly label (e.g. 'Google Gemini', 'OpenAI GPT', 'Anthropic Claude').
     */
    public function getLabel(): string;

    /**
     * Translates text into target language while preserving markdown, HTML, and code blocks.
     *
     * @param string $text
     * @param string $targetLang
     * @param string $sourceLang
     * @return string
     */
    public function translate(string $text, string $targetLang, string $sourceLang = 'en'): string;

    /**
     * Checks if provider has required configuration (API key or local endpoint).
     */
    public function isAvailable(): bool;
}
