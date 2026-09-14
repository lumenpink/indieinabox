<?php

declare(strict_types=1);

namespace Indieinabox\Webmention;

/**
 * Class LinkExtractor
 *
 * Extracts and filters outbound links from post frontmatter and content for Webmention dispatching.
 */
class LinkExtractor
{
    /**
     * Standard frontmatter interaction properties that may contain external target URLs.
     */
    public const INTERACTION_PROPERTIES = [
        'in-reply-to',
        'like-of',
        'repost-of',
        'bookmark-of'
    ];

    /**
     * Extracts, deduplicates, and filters outgoing URLs from frontmatter and content.
     * Automatically filters out self-pings matching the source host.
     *
     * @param string $sourceUrl The permalink of the local post.
     * @param array<string, mixed> $frontmatter Post frontmatter metadata.
     * @param string $content Post Markdown or HTML body.
     * @return string[] Array of unique, valid target URLs.
     */
    public static function extractLinks(string $sourceUrl, array $frontmatter, string $content): array
    {
        $links = array_merge(
            self::extractFromFrontmatter($frontmatter),
            self::extractFromContent($content)
        );

        $unique = array_values(array_unique($links));
        return self::filterSelfPings($sourceUrl, $unique);
    }

    /**
     * Extracts URLs defined in standard frontmatter interaction properties.
     *
     * @param array<string, mixed> $frontmatter
     * @return string[]
     */
    public static function extractFromFrontmatter(array $frontmatter): array
    {
        $links = [];
        foreach (self::INTERACTION_PROPERTIES as $prop) {
            if (empty($frontmatter[$prop])) {
                continue;
            }

            $urls = is_array($frontmatter[$prop]) ? $frontmatter[$prop] : [$frontmatter[$prop]];
            foreach ($urls as $url) {
                if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                    $links[] = $url;
                }
            }
        }
        return $links;
    }

    /**
     * Extracts URLs from Markdown links, HTML href attributes, and bare URLs in text.
     *
     * @param string $content
     * @return string[]
     */
    public static function extractFromContent(string $content): array
    {
        $links = [];

        // 1. Markdown links: [text](url)
        if (preg_match_all('/\[[^\]]+\]\((https?:\/\/[^\)]+)\)/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $links[] = $url;
                }
            }
        }

        // 2. HTML links: href="url"
        if (preg_match_all('/href=[\'"](https?:\/\/[^\'"]+)[\'"]/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $links[] = $url;
                }
            }
        }

        // 3. Bare URLs
        if (preg_match_all('/https?:\/\/[^\s<>\)"]+/i', $content, $matches)) {
            foreach ($matches[0] as $url) {
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $links[] = $url;
                }
            }
        }

        return $links;
    }

    /**
     * Filters out target URLs that belong to the same host as the source URL (self-pings).
     *
     * @param string $sourceUrl
     * @param string[] $links
     * @return string[]
     */
    public static function filterSelfPings(string $sourceUrl, array $links): array
    {
        $sourceHost = parse_url($sourceUrl, PHP_URL_HOST);
        if (empty($sourceHost)) {
            return $links;
        }

        return array_values(array_filter($links, function (string $targetUrl) use ($sourceHost): bool {
            $targetHost = parse_url($targetUrl, PHP_URL_HOST);
            return $targetHost !== null && strcasecmp($sourceHost, $targetHost) !== 0;
        }));
    }
}
