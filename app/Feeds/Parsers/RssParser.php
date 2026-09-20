<?php

declare(strict_types=1);

namespace Indieinabox\Feeds\Parsers;

use Indieinabox\Feeds\Contracts\FeedParserInterface;

/**
 * Strategy parser for RSS feeds.
 */
class RssParser implements FeedParserInterface
{
    #[\Override]
    public function getFormat(): string
    {
        return 'rss';
    }

    #[\Override]
    public function supports(string $content): bool
    {
        $trimmed = trim($content);
        if (!str_starts_with($trimmed, '<')) {
            return false;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($trimmed);
        return $xml !== false && isset($xml->channel);
    }

    /**
     * @return array<int, array{
     *     uid: string,
     *     url: string,
     *     title: ?string,
     *     content: string,
     *     published_at: int,
     *     author: ?array<string, mixed>
     * }>
     */
    #[\Override]
    public function parse(string $content, string $feedUrl): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(trim($content));
        if ($xml === false || !isset($xml->channel)) {
            return [];
        }

        $authorName = (string)($xml->channel->title ?? 'Unknown');
        $items = [];

        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $url = (string)($item->link ?? '');
                $id = (string)($item->guid ?? $url);
                if (!$id) {
                    $id = md5((string)($item->title ?? $url));
                }

                $title = isset($item->title) ? (string)$item->title : null;
                $description = (string)($item->description ?? '');
                $published = isset($item->pubDate) ? (strtotime((string)$item->pubDate) ?: time()) : time();
                $itemAuthor = isset($item->author) ? (string)$item->author : $authorName;

                $items[] = [
                    'uid' => $id,
                    'url' => $url ?: $feedUrl,
                    'title' => $title,
                    'content' => $description,
                    'published_at' => $published,
                    'author' => ['name' => $itemAuthor],
                ];
            }
        }

        return $items;
    }
}
