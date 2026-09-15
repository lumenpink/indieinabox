<?php

declare(strict_types=1);

namespace Indieinabox\Feeds\Parsers;

use Indieinabox\Feeds\Contracts\FeedParserInterface;

/**
 * Strategy parser for Atom feeds.
 */
class AtomParser implements FeedParserInterface
{
    public function getFormat(): string
    {
        return 'atom';
    }

    public function supports(string $content): bool
    {
        $trimmed = trim($content);
        if (!str_starts_with($trimmed, '<')) {
            return false;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($trimmed);
        return $xml !== false && (isset($xml->entry) || (isset($xml->title) && !isset($xml->channel)));
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
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(trim($content));
        if ($xml === false) {
            return [];
        }

        $authorName = (string)($xml->title ?? 'Unknown');
        $authorPhoto = '';
        if (isset($xml->icon)) {
            $authorPhoto = (string)$xml->icon;
        } elseif (isset($xml->logo)) {
            $authorPhoto = (string)$xml->logo;
        }

        $items = [];
        if (isset($xml->entry)) {
            foreach ($xml->entry as $entry) {
                $id = (string)($entry->id ?? '');
                $url = '';
                if (isset($entry->link)) {
                    foreach ($entry->link as $link) {
                        if ((string)$link['rel'] === 'alternate' || empty($link['rel'])) {
                            $url = (string)$link['href'];
                            break;
                        }
                    }
                    if (!$url && isset($entry->link['href'])) {
                        $url = (string)$entry->link['href'];
                    }
                }

                $contentStr = '';
                if (isset($entry->content)) {
                    $contentStr = (string)$entry->content;
                } elseif (isset($entry->summary)) {
                    $contentStr = (string)$entry->summary;
                }

                $published = isset($entry->published)
                    ? (strtotime((string)$entry->published) ?: time())
                    : (isset($entry->updated) ? (strtotime((string)$entry->updated) ?: time()) : time());

                $entryAuthor = isset($entry->author->name) ? (string)$entry->author->name : $authorName;
                $title = isset($entry->title) ? (string)$entry->title : null;

                $items[] = [
                    'uid' => $id ?: md5($feedUrl . $url . $published),
                    'url' => $url ?: $feedUrl,
                    'title' => $title,
                    'content' => $contentStr,
                    'published_at' => $published,
                    'author' => [
                        'name' => $entryAuthor,
                        'photo' => $authorPhoto,
                    ],
                ];
            }
        }

        return $items;
    }
}
