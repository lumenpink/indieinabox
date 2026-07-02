<?php

declare(strict_types=1);

namespace Indieinabox\Markdown;

/**
 * --------------------------------------------------------------------------
 * Validation Diagnostic Models
 * --------------------------------------------------------------------------
 */

class ValidationError implements \JsonSerializable
{
    public function __construct(
        public int $line,
        public string $message
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => 'error',
            'line' => $this->line,
            'message' => $this->message,
        ];
    }
}

class ValidationWarning implements \JsonSerializable
{
    public function __construct(
        public int $line,
        public string $message
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => 'warning',
            'line' => $this->line,
            'message' => $this->message,
        ];
    }
}

class ValidationResult implements \JsonSerializable
{
    /**
     * @param bool $isValid
     * @param ValidationError[] $errors
     * @param ValidationWarning[] $warnings
     */
    public function __construct(
        public bool $isValid,
        public array $errors,
        public array $warnings
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'isValid' => $this->isValid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}

/**
 * --------------------------------------------------------------------------
 * Markdown integrity validator (Linter/Sanitizer)
 * --------------------------------------------------------------------------
 */
class MarkdownValidator
{
    /**
     * @var string
     */
    private string $markdown;

    /**
     * Method __construct
     * @param string $markdown
     */
    public function __construct(string $markdown)
    {
        $this->markdown = $markdown;
    }

    /**
     * Runs full diagnostics on the Markdown content.
     *
     * @return ValidationResult
     */
    public function validate(): ValidationResult
    {
        /** @var ValidationError[] $errors */
        $errors = [];
        /** @var ValidationWarning[] $warnings */
        $warnings = [];

        $lines = explode("\n", $this->markdown);
        $totalLines = count($lines);

        $contentStartLine = 0;

        // 1. YAML Front-Matter Integrity Verification
        if (rtrim($lines[0]) === '---') {
            $closingLineIndex = -1;
            for ($i = 1; $i < $totalLines; $i++) {
                if (rtrim($lines[$i]) === '---') {
                    $closingLineIndex = $i;
                    break;
                }
            }

            if ($closingLineIndex === -1) {
                $errors[] = new ValidationError(
                    1,
                    "Front-matter block opened but not closed (missing closing '---')."
                );
            } else {
                // Verify YAML key-value syntax inside the block
                for ($i = 1; $i < $closingLineIndex; $i++) {
                    $lineText = $lines[$i];
                    $trimmed = trim($lineText);

                    // Extract content before any comment character
                    $parts = explode('#', $trimmed, 2);
                    $contentOnly = trim($parts[0]);

                    if ($contentOnly === '') {
                        continue;
                    }
                    if ($contentOnly[0] === '-') {
                        continue;
                    }

                    if (strpos($contentOnly, ':') === false) {
                        $errors[] = new ValidationError(
                            $i + 1,
                            "Invalid YAML key-value format inside front-matter."
                        );
                    }
                }
                $contentStartLine = $closingLineIndex + 1;
            }
        }

        // 2. Headings and Inline Formatting Diagnostics
        $currentLine = $contentStartLine + 1;
        $paragraphBuffer = '';
        $paragraphStartLine = $currentLine;

        for ($i = $contentStartLine; $i < $totalLines; $i++) {
            $line = $lines[$i];
            $trimmed = trim($line);

            // Heading consistency check
            if (preg_match('/^(#{1,6})([^#\s\n].*)$/', $line)) {
                $warnings[] = new ValidationWarning(
                    $i + 1,
                    "Heading delimiter is glued to text (missing space after '#')."
                );
            }

            if ($trimmed === '') {
                // Blank line terminates active paragraph block, trigger inline scanning
                if ($paragraphBuffer !== '') {
                    $this->validateParagraphInlines($paragraphBuffer, $paragraphStartLine, $errors, $warnings);
                    $paragraphBuffer = '';
                }
                $currentLine++;
                $paragraphStartLine = $currentLine;
            } else {
                if ($paragraphBuffer !== '') {
                    $paragraphBuffer .= "\n" . $line;
                } else {
                    $paragraphBuffer = $line;
                    $paragraphStartLine = $i + 1;
                }
                $currentLine++;
            }
        }

        // Flush trailing paragraph if any
        if ($paragraphBuffer !== '') {
            $this->validateParagraphInlines($paragraphBuffer, $paragraphStartLine, $errors, $warnings);
        }

        $isValid = empty($errors);

        return new ValidationResult($isValid, $errors, $warnings);
    }

    /**
     * Performs character-by-character validation of inline formatting markers inside a paragraph block.
     *
     * @param string $text
     * @param int $startLine
     * @param ValidationError[] &$errors
     * @param ValidationWarning[] &$warnings
     * @return void
     */
    private function validateParagraphInlines(
        string $text,
        int $startLine,
        array &$errors,
        array &$warnings
    ): void {
        $len = strlen($text);
        $i = 0;
        $currentLine = $startLine;

        $boldOpen = false;
        $boldLine = 0;

        $italicAsteriskOpen = false;
        $italicAsteriskLine = 0;

        $italicUnderscoreOpen = false;
        $italicUnderscoreLine = 0;

        while ($i < $len) {
            if ($text[$i] === "\n") {
                $currentLine++;
                $i++;
                continue;
            }

            // 1. Wikilinks verification
            if ($i + 1 < $len && $text[$i] === '[' && $text[$i + 1] === '[') {
                $nextNewline = strpos($text, "\n", $i + 2);
                $searchLimit = ($nextNewline !== false) ? $nextNewline : $len;

                $closePos = strpos($text, ']]', $i + 2);
                if ($closePos === false || $closePos >= $searchLimit) {
                    $errors[] = new ValidationError($currentLine, "Unclosed wikilink.");
                    $i += 2;
                } else {
                    $inner = substr($text, $i + 2, $closePos - ($i + 2));
                    $parts = explode('|', $inner, 2);

                    $malformed = false;
                    if (count($parts) === 2) {
                        $target = trim($parts[0]);
                        if ($target === '') {
                            $malformed = true;
                        }
                    } else {
                        $target = trim($inner);
                        if ($target === '') {
                            $malformed = true;
                        }
                    }

                    if ($malformed) {
                        $errors[] = new ValidationError($currentLine, "Empty or malformed wikilink.");
                    }

                    $i = $closePos + 2;
                }
                continue;
            }

            // 2. Inline Code verification
            if ($text[$i] === '`') {
                $nextNewline = strpos($text, "\n", $i + 1);
                $searchLimit = ($nextNewline !== false) ? $nextNewline : $len;

                $closePos = strpos($text, '`', $i + 1);
                if ($closePos === false || $closePos >= $searchLimit) {
                    $warnings[] = new ValidationWarning($currentLine, "Unclosed inline code delimiter.");
                    $i++;
                } else {
                    // Skip inline code content
                    $i = $closePos + 1;
                }
                continue;
            }

            // 3. Bold/Strong checking
            if ($i + 1 < $len && $text[$i] === '*' && $text[$i + 1] === '*') {
                if ($boldOpen) {
                    $boldOpen = false;
                } else {
                    $boldOpen = true;
                    $boldLine = $currentLine;
                }
                $i += 2;
                continue;
            }

            // 4. Italic with asterisk checking
            if ($text[$i] === '*') {
                if ($italicAsteriskOpen) {
                    $italicAsteriskOpen = false;
                } else {
                    $italicAsteriskOpen = true;
                    $italicAsteriskLine = $currentLine;
                }
                $i++;
                continue;
            }

            // 5. Italic with underscore checking
            if ($text[$i] === '_') {
                if ($italicUnderscoreOpen) {
                    $italicUnderscoreOpen = false;
                } else {
                    $italicUnderscoreOpen = true;
                    $italicUnderscoreLine = $currentLine;
                }
                $i++;
                continue;
            }

            $i++;
        }

        if ($boldOpen) {
            $warnings[] = new ValidationWarning($boldLine, "Unclosed bold delimiter (**).");
        }
        if ($italicAsteriskOpen) {
            $warnings[] = new ValidationWarning($italicAsteriskLine, "Unclosed italic delimiter (*).");
        }
        if ($italicUnderscoreOpen) {
            $warnings[] = new ValidationWarning($italicUnderscoreLine, "Unclosed italic delimiter (_).");
        }
    }
}

/**
 * --------------------------------------------------------------------------
 * Test block (only runs when script is executed directly from CLI)
 * --------------------------------------------------------------------------
 */
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $corruptedMarkdown = <<<MARKDOWN
---
title: Corrupted Note Example
tags:
  - testing
author broken yaml line  # Error YAML: No colon
---

#Título Glued Glued  # Warning Heading

Este parágrafo possui um wikilink [[Aberto sem fechamento e um código `sem fechar na mesma linha.
Além disso, temos um wikilink malformado [[]] e outro [[ | com alias inválido]].

Aqui iniciamos um **negrito que nunca é fechado até o fim do parágrafo.
MARKDOWN;

    $validator = new MarkdownValidator($corruptedMarkdown);
    $result = $validator->validate();

    echo "=== CORRUPTED MARKDOWN INBOUND ===\n";
    echo $corruptedMarkdown . "\n\n";
    echo "=== DIAGNOSTIC REPORT (JSON) ===\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
