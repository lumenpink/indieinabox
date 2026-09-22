<?php

declare(strict_types=1);

namespace Indieinabox\Localization\Engines;

use Indieinabox\Localization\Contracts\TranslationEngineInterface;

/**
 * Dictionary-based translation engine using JSON or PHP translation catalogs.
 *
 * Preserves code blocks, HTML tags, and markdown links intact while translating
 * recognized dictionary terms and falling back cleanly.
 */
class DictionaryTranslationEngine implements TranslationEngineInterface
{
    /**
     * In-memory translation dictionaries: [lang => [phrase => translation]]
     *
     * @var array<string, array<string, string>>
     */
    private array $dictionaries = [];

    /**
     * @param array<string, array<string, string>> $dictionaries
     */
    public function __construct(array $dictionaries = [])
    {
        $this->dictionaries = $dictionaries;
    }

    #[\Override]
    public function getName(): string
    {
        return 'dictionary';
    }

    /**
     * Loads a dictionary file (.json or .php array returning array<string, string>).
     */
    public function loadDictionaryFile(string $lang, string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext === 'json') {
            $data = json_decode((string)file_get_contents($filePath), true);
            if (is_array($data)) {
                $this->dictionaries[$lang] = array_merge($this->dictionaries[$lang] ?? [], $data);
                return true;
            }
        } elseif ($ext === 'php') {
            $data = require $filePath;
            if (is_array($data)) {
                $this->dictionaries[$lang] = array_merge($this->dictionaries[$lang] ?? [], $data);
                return true;
            }
        }

        return false;
    }

    /**
     * Registers an in-memory dictionary for a specific language.
     *
     * @param string $lang
     * @param array<string, string> $catalog
     */
    public function setDictionary(string $lang, array $catalog): void
    {
        $this->dictionaries[$lang] = $catalog;
    }

    #[\Override]
    public function translateText(string $text, string $targetLang, string $sourceLang = 'en'): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return $text;
        }

        $catalog = $this->dictionaries[$targetLang] ?? [];
        if (isset($catalog[$trimmed])) {
            return str_replace($trimmed, $catalog[$trimmed], $text);
        }

        // Case-insensitive lookup
        foreach ($catalog as $key => $val) {
            if (strcasecmp($key, $trimmed) === 0) {
                return str_replace($trimmed, $val, $text);
            }
        }

        // Fallback: graceful label prefix indicating virtualization
        return '[' . strtoupper($targetLang) . '] ' . $text;
    }

    #[\Override]
    public function translateContent(string $content, string $targetLang, string $sourceLang = 'en'): string
    {
        if (trim($content) === '') {
            return $content;
        }

        // Protect code blocks (``` ... ``` and ` ... `) and HTML tags (<...>)
        $tokens = [];
        $tokenIndex = 0;

        // 1. Mask fenced code blocks
        $masked = preg_replace_callback('/```[\s\S]*?```/u', function ($match) use (&$tokens, &$tokenIndex) {
            $key = "\x00CODE_BLOCK_{$tokenIndex}\x00";
            $tokens[$key] = $match[0];
            $tokenIndex++;
            return $key;
        }, $content);

        // 2. Mask inline code
        $masked = preg_replace_callback('/`[^`]+`/u', function ($match) use (&$tokens, &$tokenIndex) {
            $key = "\x00INLINE_CODE_{$tokenIndex}\x00";
            $tokens[$key] = $match[0];
            $tokenIndex++;
            return $key;
        }, (string)$masked);

        // 3. Mask HTML tags
        $masked = preg_replace_callback('/<[^>]+>/u', function ($match) use (&$tokens, &$tokenIndex) {
            $key = "\x00HTML_TAG_{$tokenIndex}\x00";
            $tokens[$key] = $match[0];
            $tokenIndex++;
            return $key;
        }, (string)$masked);

        // Translate recognized catalog keys
        $catalog = $this->dictionaries[$targetLang] ?? [];
        if (!empty($catalog)) {
            // Sort by descending string length to avoid partial replacements
            uksort($catalog, function ($a, $b) {
                return strlen($b) <=> strlen($a);
            });

            foreach ($catalog as $sourcePhrase => $transPhrase) {
                if ($sourcePhrase !== '') {
                    $pattern = '/(?<![a-zA-Z0-9_])' . preg_quote($sourcePhrase, '/') . '(?![a-zA-Z0-9_])/ui';
                    $masked = preg_replace($pattern, $transPhrase, (string)$masked);
                }
            }
        }

        // If no translation occurred and there's no exact match, fallback gracefully
        if ($masked === $content && !str_starts_with($content, '[' . strtoupper($targetLang) . ']')) {
            $masked = '[' . strtoupper($targetLang) . '] ' . $masked;
        }

        // Restore masked tokens
        foreach ($tokens as $tokenKey => $original) {
            $masked = str_replace($tokenKey, $original, (string)$masked);
        }

        return (string)$masked;
    }
}
