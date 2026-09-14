<?php

declare(strict_types=1);

namespace Indieinabox\Webmention;

use DOMDocument;
use DOMXPath;
use Indieinabox\Whostyles;
use Mf2;

/**
 * Class PayloadParser
 *
 * Parses remote HTML payloads using Microformats 2 (mf2) to extract rich IndieWeb metadata
 * (author h-card, photo, name, interaction types, and sanitized content).
 */
class PayloadParser
{
    /**
     * Parses source HTML and extracts normalized interaction and author metadata.
     *
     * @param string $html The fetched source HTML.
     * @param string $sourceUrl The source URL for relative reference resolution.
     * @param ?string $targetUrl The target URL to match interaction properties against.
     * @return array{
     *     title: string,
     *     text: string,
     *     html: string,
     *     author_name: string,
     *     author_photo: string,
     *     author_url: string,
     *     interaction_type: string,
     *     rsvp: ?string,
     *     published: ?string,
     *     whostyle: ?array<array-key, mixed>
     * }
     */
    public static function parse(string $html, string $sourceUrl, ?string $targetUrl = null): array
    {
        $parsed = null;
        if (class_exists('Mf2\Parser')) {
            $parsed = Mf2\parse($html, $sourceUrl);
        }

        $items = $parsed['items'] ?? [];
        $entry = self::findItemByType($items, 'h-entry') ?? ($items[0] ?? null);

        // 1. Author extraction (h-card)
        $author = self::extractAuthor($entry, $items, $sourceUrl);

        // 2. Title extraction
        $title = self::extractTitle($entry, $html);

        // 3. Content extraction
        $contentData = self::extractContent($entry, $html);

        // 4. Interaction type
        $interaction = self::detectInteractionType($entry, $targetUrl, $sourceUrl);

        // 5. Published date
        $published = $entry['properties']['published'][0] ?? null;

        // 6. Whostyle extraction
        $whostyleData = null;
        $hash = Whostyles::extract($html);
        if ($hash) {
            $whostyleData = Whostyles::decode($hash);
        }

        $authorName = $author['name'];
        if ($authorName === '') {
            $defaultHost = (string)parse_url($sourceUrl, PHP_URL_HOST);
            $authorName = $title !== '' ? $title : ('Webmention from ' . ($defaultHost !== '' ? $defaultHost : 'external link'));
        }

        return [
            'title' => $title,
            'text' => $contentData['text'],
            'html' => $contentData['html'],
            'author_name' => $authorName,
            'author_photo' => $author['photo'],
            'author_url' => $author['url'],
            'interaction_type' => $interaction['type'],
            'rsvp' => $interaction['rsvp'],
            'published' => is_string($published) ? $published : null,
            'whostyle' => $whostyleData
        ];
    }

    /**
     * Finds the first microformat item matching a given type prefix.
     *
     * @param array<int, mixed> $items
     * @param string $type
     * @return array<string, mixed>|null
     */
    private static function findItemByType(array $items, string $type): ?array
    {
        foreach ($items as $item) {
            if (isset($item['type']) && is_array($item['type']) && in_array($type, $item['type'], true)) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Extracts author details (name, photo, URL) from entry or top-level h-cards.
     *
     * @param array<string, mixed>|null $entry
     * @param array<int, mixed> $items
     * @param string $sourceUrl
     * @return array{name: string, photo: string, url: string}
     */
    public static function extractAuthor(?array $entry, array $items, string $sourceUrl): array
    {
        $authorCard = null;

        // 1. Check nested p-author inside entry
        if ($entry && isset($entry['properties']['author'][0])) {
            $rawAuthor = $entry['properties']['author'][0];
            if (is_array($rawAuthor) && isset($rawAuthor['type']) && in_array('h-card', $rawAuthor['type'], true)) {
                $authorCard = $rawAuthor;
            } elseif (is_string($rawAuthor)) {
                return [
                    'name' => trim($rawAuthor),
                    'photo' => '',
                    'url' => filter_var($rawAuthor, FILTER_VALIDATE_URL) ? $rawAuthor : ''
                ];
            }
        }

        // 2. Check top-level h-card if not found in entry
        if (!$authorCard) {
            $authorCard = self::findItemByType($items, 'h-card');
        }

        if ($authorCard && isset($authorCard['properties'])) {
            $props = $authorCard['properties'];

            $rawName = $props['name'][0] ?? '';
            $name = is_array($rawName) ? (string)($rawName['value'] ?? '') : (string)$rawName;

            $rawPhoto = $props['photo'][0] ?? '';
            $photo = is_array($rawPhoto) ? (string)($rawPhoto['value'] ?? '') : (string)$rawPhoto;

            $rawUrl = $props['url'][0] ?? '';
            $url = is_array($rawUrl) ? (string)($rawUrl['value'] ?? '') : (string)$rawUrl;

            return [
                'name' => trim($name),
                'photo' => trim($photo),
                'url' => trim($url)
            ];
        }

        return [
            'name' => '',
            'photo' => '',
            'url' => ''
        ];
    }

    /**
     * Extracts title from entry properties or HTML <title> tag.
     *
     * @param array<string, mixed>|null $entry
     * @param string $html
     * @return string
     */
    private static function extractTitle(?array $entry, string $html): string
    {
        if ($entry && isset($entry['properties']['name'][0]) && is_string($entry['properties']['name'][0])) {
            $name = trim($entry['properties']['name'][0]);
            // If name is not a massive dump of text (common in microblog notes where name == content)
            if ($name !== '' && strlen($name) < 200) {
                return $name;
            }
        }

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        $titleNode = $xpath->query('//title')->item(0);

        return $titleNode ? trim($titleNode->nodeValue) : '';
    }

    /**
     * Extracts text and HTML content from entry e-content or DOM fallback.
     *
     * @param array<string, mixed>|null $entry
     * @param string $html
     * @return array{text: string, html: string}
     */
    private static function extractContent(?array $entry, string $html): array
    {
        if ($entry && isset($entry['properties']['content'][0])) {
            $rawContent = $entry['properties']['content'][0];
            if (is_array($rawContent)) {
                $text = (string)($rawContent['value'] ?? '');
                $contentHtml = (string)($rawContent['html'] ?? '');
                return [
                    'text' => trim($text),
                    'html' => trim($contentHtml)
                ];
            }
            if (is_string($rawContent)) {
                return [
                    'text' => trim($rawContent),
                    'html' => nl2br(htmlspecialchars(trim($rawContent)))
                ];
            }
        }

        // Summary fallback
        if ($entry && isset($entry['properties']['summary'][0]) && is_string($entry['properties']['summary'][0])) {
            $summary = trim($entry['properties']['summary'][0]);
            return [
                'text' => $summary,
                'html' => htmlspecialchars($summary)
            ];
        }

        // Fallback to DOM XPath
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $node = $xpath->query('//*[contains(@class, "e-content")]')->item(0) ?? $xpath->query('//p')->item(0);
        $text = $node ? trim($node->nodeValue) : '';

        return [
            'text' => $text,
            'html' => htmlspecialchars($text)
        ];
    }

    /**
     * Detects IndieWeb interaction type (like, repost, reply, bookmark, rsvp, or webmention).
     *
     * @param array<string, mixed>|null $entry
     * @param ?string $targetUrl
     * @param string $sourceUrl
     * @return array{type: string, rsvp: ?string}
     */
    public static function detectInteractionType(?array $entry, ?string $targetUrl, string $sourceUrl): array
    {
        if (!$entry || !isset($entry['properties'])) {
            return ['type' => 'webmention', 'rsvp' => null];
        }

        $props = $entry['properties'];
        $verifier = new SourceVerifier();

        $checkProp = function (string $key) use ($props, $targetUrl, $sourceUrl, $verifier): bool {
            if (empty($props[$key]) || $targetUrl === null) {
                return false;
            }
            $urls = is_array($props[$key]) ? $props[$key] : [$props[$key]];
            foreach ($urls as $url) {
                if (is_string($url) && $verifier->urlsMatch($url, $targetUrl, $sourceUrl)) {
                    return true;
                }
            }
            return false;
        };

        if ($checkProp('like-of')) {
            return ['type' => 'like', 'rsvp' => null];
        }

        if ($checkProp('repost-of')) {
            return ['type' => 'repost', 'rsvp' => null];
        }

        if ($checkProp('bookmark-of')) {
            return ['type' => 'bookmark', 'rsvp' => null];
        }

        if ($checkProp('in-reply-to')) {
            $rsvp = $props['rsvp'][0] ?? null;
            return [
                'type' => $rsvp ? 'rsvp' : 'reply',
                'rsvp' => is_string($rsvp) ? strtolower($rsvp) : null
            ];
        }

        if (!empty($props['rsvp'][0])) {
            return [
                'type' => 'rsvp',
                'rsvp' => strtolower((string)$props['rsvp'][0])
            ];
        }

        return ['type' => 'webmention', 'rsvp' => null];
    }
}
