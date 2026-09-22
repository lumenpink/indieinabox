<?php

declare(strict_types=1);

namespace Indieinabox\Localization\Contracts;

/**
 * Interface defining pluggable translation engines for localized content virtualization.
 */
interface TranslationEngineInterface
{
    /**
     * Unique identifier for the translation engine (e.g. 'dictionary', 'machine', 'pseudo').
     */
    public function getName(): string;

    /**
     * Translates a plain text string into the target language.
     *
     * @param string $text Source text to translate.
     * @param string $targetLang Target BCP-47 or ISO language code.
     * @param string $sourceLang Source language code.
     * @return string
     */
    public function translateText(string $text, string $targetLang, string $sourceLang = 'en'): string;

    /**
     * Translates markdown or HTML content, preserving tags, code blocks, and markdown structure.
     *
     * @param string $content Source body text.
     * @param string $targetLang Target language code.
     * @param string $sourceLang Source language code.
     * @return string
     */
    public function translateContent(string $content, string $targetLang, string $sourceLang = 'en'): string;
}
