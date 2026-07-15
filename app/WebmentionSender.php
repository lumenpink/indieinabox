<?php
declare(strict_types=1);

namespace Indieinabox;

class WebmentionSender
{
    /**
     * Extracts outgoing links and queues them for webmention sending.
     * 
     * @param string $sourceUrl The URL of the post we just created.
     * @param array<string, mixed> $frontmatter The frontmatter of the post.
     * @param string $content The Markdown or HTML content of the post.
     */
    public static function queueOutgoingWebmentions(string $sourceUrl, array $frontmatter, string $content): void
    {
        $links = [];

        // 1. Extract from frontmatter properties (in-reply-to, like-of, repost-of, etc)
        $props = ['in-reply-to', 'like-of', 'repost-of', 'bookmark-of'];
        foreach ($props as $prop) {
            if (!empty($frontmatter[$prop])) {
                $urls = is_array($frontmatter[$prop]) ? $frontmatter[$prop] : [$frontmatter[$prop]];
                foreach ($urls as $url) {
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        $links[] = $url;
                    }
                }
            }
        }

        // 2. Extract from content (regex for Markdown links and URLs)
        // Markdown links: [text](url)
        preg_match_all('/\[[^\]]+\]\((https?:\/\/[^\)]+)\)/i', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $links[] = $url;
                }
            }
        }

        // HTML links: href="url"
        preg_match_all('/href=[\'"](https?:\/\/[^\'"]+)[\'"]/i', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $url) {
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $links[] = $url;
                }
            }
        }

        // Bare URLs (simple regex)
        preg_match_all('/https?:\/\/[^\s<>\)"]+/i', $content, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $url) {
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $links[] = $url;
                }
            }
        }

        $links = array_unique($links);

        $db = Database::getDb();
        $stmt = $db->prepare('INSERT INTO outgoing_webmentions (source_url, target_url, created_at) VALUES (:source, :target, :time)');
        
        $now = time();
        foreach ($links as $targetUrl) {
            // Prevent self-pinging (unless they really want to, but usually bad)
            $sourceHost = parse_url($sourceUrl, PHP_URL_HOST);
            $targetHost = parse_url($targetUrl, PHP_URL_HOST);
            if ($sourceHost === $targetHost) {
                continue;
            }

            // Check if we already queued this exact pair recently to prevent spam
            $check = $db->prepare('SELECT id FROM outgoing_webmentions WHERE source_url = ? AND target_url = ?');
            $check->execute([$sourceUrl, $targetUrl]);
            if ($check->fetch()) {
                continue;
            }

            $stmt->bindValue(':source', $sourceUrl);
            $stmt->bindValue(':target', $targetUrl);
            $stmt->bindValue(':time', $now);
            $stmt->execute();
        }
    }
}
