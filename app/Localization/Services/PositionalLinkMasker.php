<?php

declare(strict_types=1);

namespace Indieinabox\Localization\Services;

/**
 * Class PositionalLinkMasker
 *
 * Masks Markdown links, images, and code blocks before sending text to AI models,
 * resolving internal cross-links to their translated counterparts and restoring them
 * strictly by position to guarantee link integrity.
 */
class PositionalLinkMasker
{
    /**
     * Masks links, images, and code blocks in content.
     *
     * @param string $content
     * @param callable|null $urlResolver fn(string $url): string Optional callback to rewrite internal URLs
     * @return array{maskedContent: string, tokens: array<string, string>}
     */
    public static function mask(string $content, ?callable $urlResolver = null): array
    {
        $tokens = [];
        $counter = 0;

        // 1. Mask fenced code blocks (``` ... ```)
        $content = (string) preg_replace_callback('/```[\s\S]*?```/u', function ($match) use (&$tokens, &$counter) {
            $token = "__CODE_BLOCK_{$counter}__";
            $tokens[$token] = $match[0];
            $counter++;
            return $token;
        }, $content);

        // 2. Mask inline code (`...`)
        $content = (string) preg_replace_callback('/`[^`\r\n]+`/u', function ($match) use (&$tokens, &$counter) {
            $token = "__INLINE_CODE_{$counter}__";
            $tokens[$token] = $match[0];
            $counter++;
            return $token;
        }, $content);

        // 3. Mask image targets: ![alt](url "title")
        $content = (string) preg_replace_callback('/!\[(?<alt>[^\]]*)\]\((?<url>[^\)\s]+)(?:\s+"(?<title>[^"]*)")?\)/u', function ($match) use (&$tokens, &$counter, $urlResolver) {
            $alt = $match['alt'];
            $url = $match['url'];
            $title = $match['title'] ?? null;

            $resolvedUrl = $urlResolver ? ($urlResolver)($url) : $url;
            $token = "__IMG_TARGET_{$counter}__";
            $tokens[$token] = $resolvedUrl . ($title !== null ? ' "' . $title . '"' : '');
            $counter++;

            return "![{$alt}]({$token})";
        }, $content);

        // 4. Mask link targets: [text](url "title") (ensure not preceded by ! to avoid matching images)
        $content = (string) preg_replace_callback('/(?<!\!)\[(?<text>[^\]]+)\]\((?<url>[^\)\s]+)(?:\s+"(?<title>[^"]*)")?\)/u', function ($match) use (&$tokens, &$counter, $urlResolver) {
            $text = $match['text'];
            $url = $match['url'];
            $title = $match['title'] ?? null;

            $resolvedUrl = $urlResolver ? ($urlResolver)($url) : $url;
            $token = "__LNK_TARGET_{$counter}__";
            $tokens[$token] = $resolvedUrl . ($title !== null ? ' "' . $title . '"' : '');
            $counter++;

            return "[{$text}]({$token})";
        }, $content);

        return [
            'maskedContent' => $content,
            'tokens' => $tokens,
        ];
    }

    /**
     * Restores all masked tokens in translated content.
     *
     * @param string $translatedContent
     * @param array<string, string> $tokens
     * @return string
     */
    public static function restore(string $translatedContent, array $tokens): string
    {
        if (empty($tokens)) {
            return $translatedContent;
        }

        return strtr($translatedContent, $tokens);
    }
}
