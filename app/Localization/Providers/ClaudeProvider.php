<?php

declare(strict_types=1);

namespace Indieinabox\Localization\Providers;

use Indieinabox\Localization\Contracts\AiTranslationProviderInterface;
use Throwable;

/**
 * Anthropic Claude Translation Provider.
 * Supports Claude 3.5 Sonnet, Claude 3.5 Haiku, and Claude 3 Opus.
 */
class ClaudeProvider implements AiTranslationProviderInterface
{
    private string $apiKey;
    private string $model;
    /** @var callable|null */
    private $transport;

    public function __construct(
        string $apiKey = '',
        string $model = 'claude-3-5-haiku-20241022',
        ?callable $transport = null
    ) {
        $this->apiKey = trim($apiKey);
        $this->model = trim($model) ?: 'claude-3-5-haiku-20241022';
        $this->transport = $transport;
    }

    #[\Override]
    public function getName(): string
    {
        return 'claude';
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'Anthropic Claude';
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

        $systemPrompt = "You are a professional literary translator for Markdown blog posts and web articles. Translate accurately from {$sourceLang} into {$targetLang}. Keep all Markdown headers, blockquotes, lists, links, footnotes, code fences (```...```), inline code (`...`), HTML tags (<...>), shortcodes, and frontmatter delimiters completely intact and untranslated. Output ONLY the translated text without commentary or preamble.";

        $endpoint = 'https://api.anthropic.com/v1/messages';
        $payload = [
            'model' => $this->model,
            'max_tokens' => 4096,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $text]
            ],
            'temperature' => 0.2
        ];

        $jsonPayload = (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01'
        ];

        try {
            if ($this->transport !== null) {
                $resp = ($this->transport)($endpoint, $headers, $jsonPayload);
                $body = $resp['body'] ?? '';
            } else {
                $body = $this->httpPost($endpoint, $headers, $jsonPayload);
            }

            if ($body !== '') {
                $decoded = json_decode($body, true);
                $blockText = $decoded['content'][0]['text'] ?? null;
                if ($blockText !== null && is_string($blockText)) {
                    return \Indieinabox\Localization\Services\AiResponseSanitizer::sanitize($blockText);
                }
            }
        } catch (Throwable) {
            // Fallback
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
        $result = curl_exec($ch);
        if (\PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }
        return is_string($result) ? $result : '';
    }
}
