<?php

declare(strict_types=1);

namespace Indieinabox\Twtxt;

use DateTime;
use DateTimeZone;
use Indieinabox\Page;
use Indieinabox\Site\Twtxt as TwtxtConfig;

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
     * Formats a Page object content into a twtxt message based on its kind.
     *
     * @param Page $page
     * @param string $fqdn
     * @return string
     */
    public static function formatPageToTwtxtMessage(Page $page, string $fqdn): string
    {
        $postUrl = rtrim($fqdn, '/') . '/' . ltrim($page->slug, '/');
        
        $displayMode = \Indieinabox\Helper::getKindConfig($page->kind)['display_mode'] ?? 'default';

        if ($displayMode === 'full_content') {
            $content = self::cleanMessage($page->rawBody ?? '');
            return preg_replace('/^\[[A-Z]{2,}\]\s+/', '', $content) ?? '';
        }

        if ($displayMode === 'thumbnail_snippet') {
            $caption = self::cleanMessage($page->rawBody ?? '');
            if ($caption === '') {
                $caption = $page->title;
            }
            
            // Remove virtual translation prefix (e.g. "[PT] ")
            $caption = preg_replace('/^\[[A-Z]{2,}\]\s+/', '', $caption);
            
            if (strlen($caption) > 140) {
                $caption = mb_substr($caption, 0, 137) . '...';
            }

            $imageUrl = '';
            if (!empty($page->images)) {
                $img = $page->images[0];
                if (preg_match('/^https?:\/\//i', $img)) {
                    $imageUrl = $img;
                } else {
                    $imageUrl = rtrim($fqdn, '/') . '/' . ltrim($img, '/');
                }
            }

            if ($imageUrl !== '') {
                return "{$caption} {$imageUrl} - {$postUrl}";
            }
            return "{$caption} - {$postUrl}";
        }

        // Articles / Generic Pages
        $title = $page->title;
        $rawBody = $page->rawBody ?? '';
        
        // Remove virtual translation prefix (e.g. "[PT] ") for cleaner language-specific feeds
        $title = preg_replace('/^\[[A-Z]{2,}\]\s+/', '', $title);
        
        $snippet = self::cleanMessage($rawBody);
        $snippet = preg_replace('/^\[[A-Z]{2,}\]\s+/', '', $snippet);
        
        if (strlen($snippet) > 100) {
            $snippet = mb_substr($snippet, 0, 97) . '...';
        }

        if ($snippet !== '') {
            return "{$title}: {$snippet} - {$postUrl}";
        }
        return "{$title} - {$postUrl}";
    }

    /**
     * Generates a twtxt.txt feed and writes it to the output file.
     *
     * @param Page[] $pages
     * @param string $outputFile
     * @param string $fqdn
     * @param TwtxtConfig $config
     * @return void
     */
    public function generateFeed(array $pages, string $outputFile, string $fqdn, TwtxtConfig $config): void
    {
        $feedContent = '';

        // Add standard metadata comments
        if ($config->nick !== '') {
            $feedContent .= "# nick = {$config->nick}\n";
        }
        if ($config->description !== '') {
            $feedContent .= "# description = {$config->description}\n";
        }
        if ($config->avatar !== '') {
            $feedContent .= "# avatar = {$config->avatar}\n";
        }
        foreach ($config->following as $follow) {
            $feedContent .= "# follow = {$follow['nick']} {$follow['url']}\n";
        }
        if ($feedContent !== '') {
            $feedContent .= "\n";
        }

        // Filter: only include kinds configured to show on home (or remove generic)
        $filteredPages = array_filter($pages, function (Page $page) {
            if (in_array("draft", $page->metadata->tags)) {
                return false;
            }
            return \Indieinabox\Helper::removegeneric($page);
        });

        // Sort chronologically (oldest first)
        usort($filteredPages, function (Page $a, Page $b) {
            return $a->date <=> $b->date;
        });

        foreach ($filteredPages as $page) {
            $date = clone $page->date;
            $date->setTimezone(new DateTimeZone('UTC'));
            $timestamp = $date->format('Y-m-d\TH:i:s\Z');
            $message = self::formatPageToTwtxtMessage($page, $fqdn);

            if ($message !== '') {
                $feedContent .= "{$timestamp}\t{$message}\n";
            }
        }

        file_put_contents($outputFile, $feedContent);
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
            '/@(?:&amp;)?lt;([^\s&]+)\s+([^\s&]+)(?:&amp;)?gt;/',
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
     * Parses a twtxt feed string into structured TwtxtEntry objects.
     *
     * @param string $content
     * @param string $defaultNick
     * @return TwtxtEntry[]
     */
    public static function parseFeedContent(string $content, string $defaultNick): array
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
                $timestamp = new DateTime($timestampStr);
            } catch (\Exception $e) {
                continue;
            }

            // Detect hub mentions sender info prefix e.g. "alice https://url: message"
            $nick = $defaultNick;
            if (preg_match('/^([^\s:]+)\s+(https?:\/\/[^\s:]+):\s*(.*)$/i', $message, $matches)) {
                $nick = $matches[1];
                $message = $matches[3];
            }

            $html = self::formatMessageToHtml($message);
            $entries[] = new TwtxtEntry($timestamp, $nick, $message, $html);
        }

        return $entries;
    }

    /**
     * Fetches timeline updates from remote feeds.
     *
     * @param array<int, array<string, string>> $following
     * @param string $cacheDir
     * @return TwtxtEntry[]
     */
    public function fetchTimeline(array $following, string $cacheDir): array
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

            $feedContent = self::fetchUrl($url);

            if ($feedContent !== false) {
                // Save to cache
                file_put_contents($cacheFile, $feedContent);
            } elseif (is_file($cacheFile)) {
                // Read from cache
                $feedContent = file_get_contents($cacheFile);
            }

            if ($feedContent) {
                $entries = self::parseFeedContent($feedContent, $nick);
                $allEntries = array_merge($allEntries, $entries);
            }
        }

        // Sort reverse-chronologically (newest first)
        usort($allEntries, function (TwtxtEntry $a, TwtxtEntry $b) {
            return $b->timestamp <=> $a->timestamp;
        });

        return $allEntries;
    }

    /**
     * Queries all configured hubs to fetch replies/mentions.
     *
     * @param array<int, string> $hubs
     * @param string $fqdn
     * @return TwtxtEntry[]
     */
    public function fetchHubMentions(array $hubs, string $fqdn): array
    {
        /** @var TwtxtEntry[] $allMentions */
        $allMentions = [];
        $feedUrl = rtrim($fqdn, '/') . '/twtxt.txt';

        foreach ($hubs as $hub) {
            $hub = rtrim($hub, '/');
            $endpoint = "{$hub}/api/plain/mentions?url=" . urlencode($feedUrl);
            $content = self::fetchUrl($endpoint);

            if ($content) {
                $entries = self::parseFeedContent($content, 'hub_mention');
                $allMentions = array_merge($allMentions, $entries);
            }
        }

        // Deduplicate mentions by message and timestamp
        $deduped = [];
        $seen = [];
        foreach ($allMentions as $entry) {
            $key = $entry->timestamp->getTimestamp() . '_' . md5($entry->message);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $deduped[] = $entry;
            }
        }

        // Sort reverse-chronologically (newest first)
        usort($deduped, function (TwtxtEntry $a, TwtxtEntry $b) {
            return $b->timestamp <=> $a->timestamp;
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
