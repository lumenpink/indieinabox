<?php

declare(strict_types=1);

namespace Indieinabox\Localization\Providers;

use Indieinabox\Localization\Contracts\AiTranslationProviderInterface;
use Throwable;

/**
 * Universal OpenAI-compatible Translation Provider.
 * Directly supports OpenAI, xAI Grok, Ollama (Local), Mistral, and DeepSeek.
 */
class OpenAiCompatibleProvider implements AiTranslationProviderInterface
{
    private string $name;
    private string $label;
    private string $endpoint;
    private string $apiKey;
    private string $model;
    /** @var callable|null */
    private $transport;

    public function __construct(
        string $name = 'openai',
        string $label = 'OpenAI GPT',
        string $endpoint = 'https://api.openai.com/v1/chat/completions',
        string $apiKey = '',
        string $model = 'gpt-4o-mini',
        ?callable $transport = null
    ) {
        $this->name = $name;
        $this->label = $label;
        $this->endpoint = $endpoint;
        $this->apiKey = trim($apiKey);
        $this->model = trim($model) ?: 'gpt-4o-mini';
        $this->transport = $transport;
    }

    #[\Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function getLabel(): string
    {
        return $this->label;
    }

    #[\Override]
    public function isAvailable(): bool
    {
        // Ollama local endpoint doesn't require an API key
        if ($this->name === 'ollama') {
            return $this->endpoint !== '';
        }
        return $this->apiKey !== '';
    }

    #[\Override]
    public function translate(string $text, string $targetLang, string $sourceLang = 'en'): string
    {
        if (trim($text) === '' || !$this->isAvailable()) {
            return $text;
        }

        $systemPrompt = "You are a specialized literary translator for Markdown blogs and articles. Translate accurately from {$sourceLang} into {$targetLang}. Keep all Markdown headers, blockquotes, lists, links, footnotes, code fences (```...```), inline code (`...`), HTML tags (<...>), shortcodes, and frontmatter delimiters completely intact and untranslated. Output ONLY the translated content without preamble or extra commentary.";

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $text]
            ],
            'temperature' => 0.2
        ];

        $jsonPayload = (string)json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = [
            'Content-Type: application/json'
        ];
        if ($this->apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        try {
            if ($this->transport !== null) {
                $resp = ($this->transport)($this->endpoint, $headers, $jsonPayload);
                $body = $resp['body'] ?? '';
            } else {
                $body = $this->httpPost($this->endpoint, $headers, $jsonPayload);
            }

            if ($body !== '') {
                $decoded = json_decode($body, true);
                $msg = $decoded['choices'][0]['message']['content'] ?? null;
                if ($msg !== null && is_string($msg)) {
                    return \Indieinabox\Localization\Services\AiResponseSanitizer::sanitize($msg);
                }
            }
        } catch (Throwable) {
            // Graceful fallback
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        $result = curl_exec($ch);
        if (\PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }
        return is_string($result) ? $result : '';
    }
}
