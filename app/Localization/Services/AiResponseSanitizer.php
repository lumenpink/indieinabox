<?php

declare(strict_types=1);

namespace Indieinabox\Localization\Services;

/**
 * Class AiResponseSanitizer
 *
 * Removes conversational pleasantries, preambles, closing chatter,
 * and accidental outer markdown code fences from AI model outputs.
 */
class AiResponseSanitizer
{
    /**
     * Sanitizes AI response text.
     */
    public static function sanitize(string $text): string
    {
        $clean = trim($text);
        if ($clean === '') {
            return '';
        }

        // 1. Strip accidental outer markdown code fence wrapping the entire output
        // e.g. ```markdown\n ... \n```
        if (preg_match('/^```(?:markdown|md)?\r?\n([\s\S]*?)\r?\n```$/iu', $clean, $matches)) {
            $clean = trim($matches[1]);
        }

        // 2. Strip conversational preambles
        $preamblePatterns = [
            '/^(?:Here\s+is|Here\'s)\s+the\s+(?:translated\s+)?(?:text|article|post|content|markdown)[\s:]*/i',
            '/^(?:Certainly|Sure|Of\s+course)[\s,!]+(?:here\s+is|here\'s)\s+the\s+(?:translation|translated\s+text)[\s:]*/i',
            '/^(?:Aqui\s+está|Segue)\s+(?:a\s+tradução|o\s+texto\s+traduzido|o\s+artigo\s+traduzido)[\s:]*/iu',
            '/^(?:Claro|Com\s+certeza)[\s,!]+(?:aqui\s+está|segue)\s+(?:a\s+tradução|o\s+texto)[\s:]*/iu',
            '/^Tradução(?:\s+para\s+[\p{L}\s]+)?\s*:\s*/iu',
            '/^Translation(?:\s+to\s+[\p{L}\s]+)?\s*:\s*/iu',
        ];

        foreach ($preamblePatterns as $pattern) {
            $clean = preg_replace($pattern, '', $clean);
            $clean = trim((string) $clean);
        }

        // 3. Strip conversational closings / polite chatter at the end
        $closingPatterns = [
            '/(?:\r?\n\r?\n|\n)(?:Hope\s+this\s+helps[!.]*|Let\s+me\s+know\s+if\s+you\s+(?:need|have)\s+.*)[\s\S]*$/i',
            '/(?:\r?\n\r?\n|\n)(?:Espero\s+que\s+ajude[!.]*|Diga(?:-me)?\s+(?:o\s+que\s+achou|se\s+precisar\s+de\s+algo)|Qualquer\s+dúvida.*)[\s\S]*$/iu',
            '/(?:\r?\n\r?\n|\n)(?:If\s+you\s+need\s+any\s+further\s+refinements.*)[\s\S]*$/i',
        ];

        foreach ($closingPatterns as $pattern) {
            $clean = preg_replace($pattern, '', (string) $clean);
            $clean = trim((string) $clean);
        }

        return $clean;
    }
}
