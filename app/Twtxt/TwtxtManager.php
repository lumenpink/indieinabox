<?php

declare(strict_types=1);

namespace Indieinabox\Twtxt;

use DateTimeImmutable;
use Indieinabox\Entry\Entry;

/**
 * Class TwtxtManager
 */
class TwtxtManager
{
    /**
     * Cleans a message by stripping Markdown formatting and collapsing it to a single line.
     *
     * @param string $text
     * @return string
     */
    public static function cleanMessage(string $text): string
    {
        // 1. Convert standard Markdown links: [Label](URL) -> Label (URL)
        $text = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            function ($matches) {
                return "{$matches[1]} ({$matches[2]})";
            },
            $text
        );

        // 2. Convert Obsidian wikilinks with alias: [[Target|Label]] -> Label
        $text = preg_replace_callback(
            '/\[\[([^\]|]+)\|([^\]]+)\]\]/',
            function ($matches) {
                return trim($matches[2]);
            },
            $text
        );

        // 3. Convert simple Obsidian wikilinks: [[Target]] -> Target
        $text = preg_replace_callback(
            '/\[\[([^\]]+)\]\]/',
            function ($matches) {
                return trim($matches[1]);
            },
            $text
        );

        // 4. Remove bold/italics markers: **, *, _, `
        $text = str_replace(['**', '*', '_', '`'], '', $text);

        // 5. Replace newlines, carriage returns, and tabs with a space
        $text = str_replace(["\r", "\n", "\t"], " ", $text);

        // 6. Collapse multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Converts raw message text into HTML with mentions, hashtags, and links formatted.
     *
     * @param string $message
     * @return string
     */
    public static function formatMessageToHtml(string $message): string
    {
        // Escape HTML first for security
        $html = htmlspecialchars($message, ENT_QUOTES | ENT_HTML5);

        // 1. Parse twtxt mentions: @<nick url> or escaped equivalents
        $html = preg_replace_callback(
            '/@(?:&amp;|&)?lt;([^\s&]+)\s+([^\s&]+)(?:&amp;|&)?gt;/',
            function ($matches) {
                $nick = $matches[1];
                $url = htmlspecialchars_decode($matches[2]);
                return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" class="mention">@' . $nick . '</a>';
            },
            $html
        );

        // 2. Convert raw HTTP/HTTPS URLs to links
        $html = preg_replace(
            '/(?<![="])(https?:\/\/[^\s\)\>]+)/i',
            '<a href="$1" target="_blank" rel="noopener">$1</a>',
            $html
        );

        // 3. Parse hashtags: #tag
        $html = preg_replace(
            '/(?<!\w)#(\w+)/u',
            '<a href="https://hub.twtxt.org/search?tag=$1" class="hashtag">#$1</a>',
            $html
        ) ?? '';

        return $html;
    }

    /**
     * Parses a twtxt feed string into universal Entry objects.
     *
     * @param string $content
     * @param string $defaultNick
     * @param string|null $sourceUrl
     * @return Entry[]
     */
    public static function parseFeedContent(string $content, string $defaultNick, ?string $sourceUrl = null): array
    {
        $entries = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode("\t", $line, 2);
            if (count($parts) < 2) {
                continue;
            }

            $timestampStr = trim($parts[0]);
            $message = trim($parts[1]);

            try {
                $timestamp = new DateTimeImmutable($timestampStr);
            } catch (\Exception $e) {
                continue;
            }

            // Detect hub mentions sender info prefix e.g. "alice https://url: message"
            $nick = $defaultNick;
            $url = $sourceUrl;
            if (preg_match('/^([^\s:]+)\s+(https?:\/\/[^\s:]+):\s*(.*)$/i', $message, $matches)) {
                $nick = $matches[1];
                $url = $matches[2];
                $message = $matches[3];
            }

            $html = self::formatMessageToHtml($message);
            $entries[] = Entry::fromTwtxt([
                'timestamp' => $timestamp,
                'nick' => $nick,
                'url' => $url,
                'message' => $message,
                'html' => $html,
            ]);
        }

        return $entries;
    }

    /**
     * Fetches timeline updates from remote feeds.
     *
     * @param array<int, array<string, string>> $following
     * @param string $cacheDir
     * @param bool $fetchOnline If false, only reads from local cache.
     * @return Entry[]
     */
    public function fetchTimeline(array $following, string $cacheDir, bool $fetchOnline = false): array
    {
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $allEntries = [];

        foreach ($following as $follow) {
            if (!isset($follow['nick']) || !isset($follow['url'])) {
                continue;
            }

            $nick = $follow['nick'];
            $url = $follow['url'];
            $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . md5($url) . '.txt';

            $feedContent = false;
            if ($fetchOnline) {
                $feedContent = self::fetchUrl($url);
            }

            if ($feedContent !== false) {
                // Save to cache
                file_put_contents($cacheFile, $feedContent);
            } elseif (is_file($cacheFile)) {
                // Read from cache
                $feedContent = file_get_contents($cacheFile);
            }

            if ($feedContent) {
                $entries = self::parseFeedContent($feedContent, $nick, $url);
                $allEntries = array_merge($allEntries, $entries);
            }
        }

        // Sort reverse-chronologically (newest first)
        usort($allEntries, function (Entry $a, Entry $b) {
            return $b->getPublishedAt() <=> $a->getPublishedAt();
        });

        return $allEntries;
    }

    /**
     * Queries all configured hubs to fetch replies/mentions.
     *
     * @param array<int, string> $hubs
     * @param string $fqdn
     * @param string $cacheDir
     * @param bool $fetchOnline If false, only reads from local cache.
     * @return Entry[]
     */
    public function fetchHubMentions(array $hubs, string $fqdn, string $cacheDir, bool $fetchOnline = false): array
    {
        /** @var Entry[] $allMentions */
        $allMentions = [];
        $feedUrl = rtrim($fqdn, '/') . '/twtxt.txt';

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        foreach ($hubs as $hub) {
            $hub = rtrim($hub, '/');
            $endpoint = "{$hub}/api/plain/mentions?url=" . urlencode($feedUrl);
            $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'hub_' . md5($endpoint) . '.txt';

            $content = false;
            if ($fetchOnline) {
                $content = self::fetchUrl($endpoint);
            }

            if ($content !== false) {
                // Save to cache
                file_put_contents($cacheFile, $content);
            } elseif (is_file($cacheFile)) {
                // Read from cache
                $content = file_get_contents($cacheFile);
            }

            if ($content) {
                $entries = self::parseFeedContent($content, 'hub_mention');
                $allMentions = array_merge($allMentions, $entries);
            }
        }

        // Deduplicate mentions by message and timestamp
        $deduped = [];
        $seen = [];
        foreach ($allMentions as $entry) {
            $key = $entry->getPublishedAt()->getTimestamp() . '_' . md5($entry->getRawContent());
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $deduped[] = $entry;
            }
        }

        // Sort reverse-chronologically (newest first)
        usort($deduped, function (Entry $a, Entry $b) {
            return $b->getPublishedAt() <=> $a->getPublishedAt();
        });

        return $deduped;
    }

    /**
     * Helper to perform high-tolerance HTTP requests.
     *
     * @param string $url
     * @return string|false
     */
    private static function fetchUrl(string $url): string|false
    {
        $options = [
            'http' => [
                'timeout' => 2.0, // Low timeout to prevent build blocking
                'header' => "User-Agent: Indieinabox/1.0 (Twtxt Fetcher)\r\n"
            ]
        ];
        $context = stream_context_create($options);
        return @file_get_contents($url, false, $context);
    }
}
