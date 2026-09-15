<?php

declare(strict_types=1);

namespace Indieinabox\Feeds\Contracts;

/**
 * Strategy contract for parsing incoming syndication and social feeds.
 */
interface FeedParserInterface
{
    /**
     * Returns the format identifier (e.g. 'rss', 'atom', 'twtxt', 'jsonfeed', 'activitypub').
     */
    public function getFormat(): string;

    /**
     * Checks if this parser can handle the given raw feed content.
     */
    public function supports(string $content): bool;

    /**
     * Parses the feed content into a list of normalized item arrays.
     *
     * @param string $content
     * @param string $feedUrl
     * @return array<int, array{
     *     uid: string,
     *     url: string,
     *     title: ?string,
     *     content: string,
     *     published_at: int,
     *     author: ?array<string, mixed>
     * }>
     */
    public function parse(string $content, string $feedUrl): array;
}
