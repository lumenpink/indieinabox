<?php

declare(strict_types=1);

namespace Indieinabox\Feeds\Parsers;

use Indieinabox\Feeds\Contracts\FeedParserInterface;

/**
 * Strategy parser for JSON Feed format.
 */
class JsonFeedParser implements FeedParserInterface
{
    public function getFormat(): string
    {
        return 'jsonfeed';
    }

    public function supports(string $content): bool
    {
        $trimmed = trim($content);
        if (!str_starts_with($trimmed, '{')) {
            return false;
        }

        $json = json_decode($trimmed, true);
        return is_array($json)
            && isset($json['version'])
            && str_starts_with((string)$json['version'], 'https://jsonfeed.org/version/');
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
    public function parse(string $content, string $feedUrl): array
    {
        $json = json_decode(trim($content), true);
        if (!is_array($json) || empty($json['items']) || !is_array($json['items'])) {
            return [];
        }

        $feedAuthor = $json['title'] ?? 'Unknown';
        $feedAvatar = $json['icon'] ?? ($json['favicon'] ?? '');
        $items = [];

        foreach ($json['items'] as $item) {
            $id = (string)($item['id'] ?? md5(json_encode($item)));
            $url = (string)($item['url'] ?? $feedUrl);
            $contentHtml = (string)($item['content_html'] ?? ($item['content_text'] ?? ''));
            $published = isset($item['date_published'])
                ? (strtotime((string)$item['date_published']) ?: time())
                : time();

            $itemAuthor = $item['author']['name'] ?? $feedAuthor;
            $itemAvatar = $item['author']['avatar'] ?? $feedAvatar;

            $items[] = [
                'uid' => $id,
                'url' => $url,
                'title' => isset($item['title']) ? (string)$item['title'] : null,
                'content' => $contentHtml,
                'published_at' => $published,
                'author' => [
                    'name' => $itemAuthor,
                    'photo' => $itemAvatar,
                ],
            ];
        }

        return $items;
    }
}
