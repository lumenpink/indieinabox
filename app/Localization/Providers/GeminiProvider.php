<?php

declare(strict_types=1);

namespace Indieinabox\Localization\Providers;

use Indieinabox\Localization\Contracts\AiTranslationProviderInterface;
use Throwable;

/**
 * Google Gemini Translation Provider.
 * Supports Gemini 1.5 Flash, Gemini 1.5 Pro, and custom Gemini models.
 */
class GeminiProvider implements AiTranslationProviderInterface
{
    private string $apiKey;
    private string $model;
    /** @var callable|null */
    private $transport;

    /**
     * @param string $apiKey
     * @param string $model
     * @param callable|null $transport Optional HTTP client callback: fn(string $url, array $headers, string $body): array{code: int, body: string, error: string}
     */
    public function __construct(string $apiKey = '', string $model = 'gemini-1.5-flash', ?callable $transport = null)
    {
        $this->apiKey = trim($apiKey);
        $this->model = trim($model) ?: 'gemini-1.5-flash';
        $this->transport = $transport;
    }

    #[\Override]
    public function getName(): string
    {
        return 'gemini';
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Google Gemini';
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    #[\Override]
    public function translate(string $text, string $targetLang, string $sourceLang = 'en'): string
    {
        if (trim($text) === '' || !$this->isAvailable()) {
            return $text;
        }

        $systemInstruction = "You are a professional literary translator for web articles and Markdown blogs. Translate the text from {$sourceLang} into {$targetLang}. Preserve all Markdown headers, blockquotes, lists, links, footnotes, code fences (```...```), inline code (`...`), HTML tags (<...>), shortcodes, and frontmatter delimiters completely intact and untranslated. Output ONLY the translated text without commentary, pleasantries, or preamble.";

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
            ]
        ];

        $jsonPayload = (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type: application/json'];

        try {
            if ($this->transport !== null) {
                $resp = ($this->transport)($endpoint, $headers, $jsonPayload);
                $body = $resp['body'] ?? '';
            } else {
                $body = $this->httpPost($endpoint, $headers, $jsonPayload);
            }

            if ($body !== '') {
                $decoded = json_decode($body, true);
                $candidateText = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($candidateText !== null && is_string($candidateText)) {
                    return trim($candidateText);
                }
            }
        } catch (Throwable) {
            // Fallback to original text on connection failure
        }

        return $text;
    }

    private function httpPost(string $url, array $headers, string $payload): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        return is_string($result) ? $result : '';
    }
}
