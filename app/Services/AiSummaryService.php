<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Core\Database;
use Indieinabox\Localization\AiModels;
use Indieinabox\Localization\Services\AiResponseSanitizer;
use Indieinabox\Page\Page;
use Indieinabox\Repositories\FileSystemContentRepository;
use Indieinabox\Site\Site;
use Throwable;

/**
 * Class AiSummaryService
 *
 * Manages generation, synchronization, content-hash validation,
 * and frontmatter persistence of article and note summaries for
 * Digital Garden, OpenGraph, Twitter Cards, and Bluesky.
 */
class AiSummaryService
{
    private ?string $apiKey;
    private string $model;
    private ?Site $site;
    /** @var callable|null */
    private $transport;

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        ?Site $site = null,
        ?callable $transport = null
    ) {
        $this->apiKey = $apiKey ? trim($apiKey) : null;
        $this->model = $model ? trim($model) : AiModels::getDefaultModel('gemini');
        $this->site = $site;
        $this->transport = $transport;

        if ($this->apiKey === null) {
            $key = (string) (Database::getSetting('ai_translation_api_key') ?? Database::getSetting('gemini_api_key') ?? '');
            if ($key !== '') {
                $this->apiKey = $key;
            }
        }
    }

    /**
     * Computes deterministic SHA-256 hash (16 chars) of the clean body without frontmatter.
     */
    public static function computeBodyHash(string $rawBody): string
    {
        $clean = self::cleanBody($rawBody);
        return substr(hash('sha256', trim($clean)), 0, 16);
    }

    /**
     * Cleans raw body text removing frontmatter, markdown fences, and excessive whitespace.
     */
    public static function cleanBody(string $text): string
    {
        // Strip frontmatter if present
        $text = (string) preg_replace('/^---[\s\S]*?---\s*/u', '', $text);
        // Strip images and links but keep link text
        $text = (string) preg_replace('/!\[.*?\]\(.*?\)/u', '', $text);
        $text = (string) preg_replace('/\[(.*?)\]\(.*?\)/u', '$1', $text);
        // Strip code blocks and inline code
        $text = (string) preg_replace('/```[\s\S]*?```/u', '', $text);
        $text = (string) preg_replace('/`.*?`/u', '', $text);
        // Strip markdown formatting symbols
        $text = (string) preg_replace('/(\*\*|__)(.*?)\1/s', '$2', $text);
        $text = (string) preg_replace('/(\*|_)(.*?)\1/s', '$2', $text);
        $text = (string) preg_replace('/~~(.*?)~~/s', '$1', $text);
        // Strip HTML tags
        $text = strip_tags($text);
        // Strip headers, blockquotes, bullets
        $text = (string) preg_replace('/^[#>\-*+]\s+/mu', '', $text);
        // Normalize whitespace
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Determines whether a summary is stale or missing and needs regeneration.
     * Respects manual author summaries (summary_origin: 'author' or 'ai-revised').
     *
     * @param array<string, mixed> $frontmatter
     * @param string $body
     * @return bool
     */
    public static function isSummaryStale(array $frontmatter, string $body): bool
    {
        $origin = (string) ($frontmatter['summary_origin'] ?? '');
        if ($origin === 'author' || $origin === 'ai-revised') {
            // Human-authored or revised summaries are protected and never considered stale
            return false;
        }

        $summary = trim((string) ($frontmatter['summary'] ?? $frontmatter['description'] ?? ''));
        if ($summary === '') {
            return true;
        }

        $summaryHash = trim((string) ($frontmatter['summary_hash'] ?? ''));
        if ($summaryHash === '') {
            // If summary exists but no hash and no explicit origin:
            // if summary_origin === 'ai', it's stale (needs hash).
            // otherwise, treat as manual author summary.
            return ($origin === 'ai');
        }

        $currentHash = self::computeBodyHash($body);
        return ($currentHash !== $summaryHash);
    }

    /**
     * Generates a 1-sentence punchy summary (<= 150 chars) via Gemini.
     */
    public function generateSummary(string $title, string $body, string $lang = 'pt-BR'): ?string
    {
        if (empty($this->apiKey)) {
            // Fallback excerpt when API key is not configured
            return self::fallbackExcerpt($body);
        }

        $cleanText = self::cleanBody($body);
        if ($cleanText === '') {
            return $title !== '' ? $title : null;
        }

        if (mb_strlen($cleanText) > 2000) {
            $cleanText = mb_substr($cleanText, 0, 2000);
        }

        $systemInstruction = "You are a professional social media editor. Write a compelling, punchy, single-sentence summary of the article or note. Maximum 150 characters. Write in the same language as the text ({$lang}). Do NOT use quotation marks, hashtags, URLs, markdown, or introductory filler like 'This article discusses' or 'Neste post'. Output ONLY the clean plain text.";

        $userPrompt = "Title: {$title}\n\nContent:\n{$cleanText}";

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
            if ($this->transport !== null) {
                $resp = ($this->transport)($endpoint, $headers, $jsonPayload);
                $bodyResp = $resp['body'] ?? '';
            } else {
                $bodyResp = $this->httpPost($endpoint, $headers, $jsonPayload);
            }

            if ($bodyResp !== '') {
                $decoded = json_decode($bodyResp, true);
                $candidate = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if ($candidate && is_string($candidate)) {
                    $cleaned = AiResponseSanitizer::sanitize($candidate);
                    $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));
                    $cleaned = trim($cleaned, '"\'');
                    if (mb_strlen($cleaned) > 150) {
                        $cleaned = mb_substr($cleaned, 0, 147) . '...';
                    }
                    return $cleaned !== '' ? $cleaned : self::fallbackExcerpt($body);
                }
            }
        } catch (Throwable) {
            // Graceful fallback
        }

        return self::fallbackExcerpt($body);
    }

    /**
     * Fallback excerpt when AI is unavailable.
     */
    public static function fallbackExcerpt(string $body): string
    {
        $text = self::cleanBody($body);
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
     * Synchronizes and persists the summary and summary_hash into a physical Markdown file.
     * Returns true if file was updated, false otherwise.
     */
    public function syncFile(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_file($filePath)) {
            return false;
        }

        $contentRepo = new FileSystemContentRepository(null, $this->site);
        $raw = (string) file_get_contents($filePath);
        $parsed = $contentRepo->parseFrontmatterMarkdown($raw);
        $frontmatter = $parsed['frontmatter'];
        $body = $parsed['body'];

        if (!self::isSummaryStale($frontmatter, $body)) {
            return false;
        }

        $title = (string) ($frontmatter['title'] ?? basename($filePath, '.md'));
        $lang = (string) ($frontmatter['lang'] ?? 'pt-BR');

        $newSummary = $this->generateSummary($title, $body, $lang);
        if ($newSummary === null || $newSummary === '') {
            return false;
        }

        $frontmatter['summary'] = $newSummary;
        $frontmatter['summary_hash'] = self::computeBodyHash($body);
        $frontmatter['summary_origin'] = 'ai';

        $updatedMarkdown = $contentRepo->buildFrontmatterMarkdown($frontmatter, $body);
        return file_put_contents($filePath, $updatedMarkdown) !== false;
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
