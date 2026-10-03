<?php

declare(strict_types=1);

namespace Indieinabox\AtProto;

use Indieinabox\AtProto\Contracts\AtProtoClientInterface;
use Throwable;

/**
 * Class AtProtoCardBuilder
 *
 * Constructs app.bsky.embed.external card embeds for long articles and link posts.
 * Leverages Gemini API to generate concise, compelling card descriptions when configured,
 * with graceful fallback to content excerpts.
 */
class AtProtoCardBuilder
{
    private ?string $geminiApiKey;
    private string $geminiModel;
    /** @var callable|null */
    private $geminiTransport;

    public function __construct(
        ?string $geminiApiKey = null,
        string $geminiModel = 'gemini-3.5-flash',
        ?callable $geminiTransport = null
    ) {
        $this->geminiApiKey = $geminiApiKey ? trim($geminiApiKey) : null;
        $this->geminiModel = trim($geminiModel) ?: 'gemini-3.5-flash';
        $this->geminiTransport = $geminiTransport;
    }

    /**
     * Builds the app.bsky.embed.external card structure.
     *
     * @param string $uri The canonical URL of the post or external link.
     * @param string $title The card title.
     * @param string $content The article content or context for summary generation.
     * @param string|null $localImagePath Optional local path to an image for thumbnail upload.
     * @param AtProtoClientInterface|null $client Optional ATProto client for thumbnail upload.
     * @param string|null $accessJwt Access JWT token for thumbnail upload.
     * @param string|null $pdsUrl PDS URL for thumbnail upload.
     * @param string|null $customDescription Optional pre-computed description/summary.
     * @return array{'$type': string, external: array{uri: string, title: string, description: string, thumb?: array<string, mixed>}}
     */
    public function build(
        string $uri,
        string $title,
        string $content,
        ?string $localImagePath = null,
        ?AtProtoClientInterface $client = null,
        ?string $accessJwt = null,
        ?string $pdsUrl = null,
        ?string $customDescription = null
    ): array {
        $description = ($customDescription !== null && $customDescription !== '')
            ? $customDescription
            : $this->generateDescription($title, $content);

        $external = [
            'uri' => $uri,
            'title' => $title !== '' ? $title : $uri,
            'description' => $description,
        ];

        if ($localImagePath && file_exists($localImagePath) && $client && $accessJwt && $pdsUrl) {
            $blob = $this->uploadThumbnail($localImagePath, $client, $accessJwt, $pdsUrl);
            if ($blob !== null) {
                $external['thumb'] = $blob;
            }
        }

        return [
            '$type' => 'app.bsky.embed.external',
            'external' => $external,
        ];
    }

    /**
     * Generates a description using Gemini or falls back to an excerpt.
     */
    public function generateDescription(string $title, string $content): string
    {
        if ($this->geminiApiKey) {
            $geminiDesc = $this->generateWithGemini($title, $content);
            if ($geminiDesc !== null && $geminiDesc !== '') {
                return $geminiDesc;
            }
        }

        return $this->fallbackExcerpt($content);
    }

    /**
     * Calls Gemini API to generate a punchy 1-sentence teaser (<= 150 chars).
     */
    private function generateWithGemini(string $title, string $content): ?string
    {
        $cleanContent = $this->cleanMarkdown($content);
        if (mb_strlen($cleanContent) > 2000) {
            $cleanContent = mb_substr($cleanContent, 0, 2000);
        }

        $systemInstruction = "You are a professional social media editor. Write a compelling, punchy, single-sentence description of the article for a Bluesky link card preview. Maximum 150 characters. Do NOT use quotation marks, hashtags, URLs, markdown, or introductory filler like 'This article discusses'. Output ONLY the clean plain text.";

        $userPrompt = "Title: {$title}\n\nContent:\n{$cleanContent}";

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->geminiModel}:generateContent?key={$this->geminiApiKey}";

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
                        ['text' => $userPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 80,
            ]
        ];

        $jsonPayload = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type: application/json'];

        try {
            if ($this->geminiTransport !== null) {
                $resp = ($this->geminiTransport)($endpoint, $headers, $jsonPayload);
                $body = $resp['body'] ?? '';
            } else {
                $body = $this->httpPost($endpoint, $headers, $jsonPayload);
            }

            if ($body !== '') {
                $decoded = json_decode($body, true);
                $candidate = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($candidate && is_string($candidate)) {
                    $cleaned = trim(preg_replace('/\s+/', ' ', $candidate));
                    $cleaned = trim($cleaned, '"\'');
                    if (mb_strlen($cleaned) > 150) {
                        $cleaned = mb_substr($cleaned, 0, 147) . '...';
                    }
                    return $cleaned;
                }
            }
        } catch (Throwable) {
            // Graceful fallback
        }

        return null;
    }

    /**
     * Fallback excerpt: extracts first ~150 chars of cleaned plain text.
     */
    public function fallbackExcerpt(string $content): string
    {
        $text = $this->cleanMarkdown($content);
        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= 150) {
            return $text;
        }

        $cut = mb_substr($text, 0, 147);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > 80) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " \t\n\r\0\x0B.,;:") . '...';
    }

    /**
     * Cleans Markdown formatting to produce readable plain text.
     */
    public function cleanMarkdown(string $md): string
    {
        // Strip frontmatter if present
        $md = preg_replace('/^---[\s\S]*?---\s*/', '', $md);
        // Strip images and links but keep link text
        $md = preg_replace('/!\[.*?\]\(.*?\)/', '', $md);
        $md = preg_replace('/\[(.*?)\]\(.*?\)/', '$1', $md);
        // Strip code blocks and inline code
        $md = preg_replace('/```[\s\S]*?```/', '', $md);
        $md = preg_replace('/`.*?`/', '', $md);
        // Strip bold, italics, strikethrough
        $md = preg_replace('/(\*\*|__)(.*?)\1/s', '$2', $md);
        $md = preg_replace('/(\*|_)(.*?)\1/s', '$2', $md);
        $md = preg_replace('/~~(.*?)~~/s', '$1', $md);
        // Strip HTML tags
        $md = strip_tags($md);
        // Strip headers, blockquotes, bullets
        $md = preg_replace('/^[#>\-*+]\s+/m', '', $md);
        // Normalize whitespace
        $md = preg_replace('/\s+/', ' ', $md);

        return trim($md);
    }

    /**
     * Uploads thumbnail image blob to ATProto PDS.
     *
     * @return array<string, mixed>|null
     */
    private function uploadThumbnail(
        string $filePath,
        AtProtoClientInterface $client,
        string $accessJwt,
        string $pdsUrl
    ): ?array {
        $data = @file_get_contents($filePath);
        if ($data === false || $data === '') {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $filePath) : false;
        if ($finfo) {
            finfo_close($finfo);
        }
        if (!$mime || !str_starts_with((string) $mime, 'image/')) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => 'image/jpeg',
            };
        }

        $res = $client->uploadBlob($data, (string) $mime, $accessJwt, $pdsUrl);
        if ($res && isset($res['blob']) && is_array($res['blob'])) {
            return $res['blob'];
        }
        return $res;
    }

    private function httpPost(string $url, array $headers, string $payload): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $res = curl_exec($ch);
        curl_close($ch);
        return is_string($res) ? $res : '';
    }
}
