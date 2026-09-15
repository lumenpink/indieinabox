<?php

declare(strict_types=1);

namespace Indieinabox\Feeds\Parsers;

use Indieinabox\Feeds\Contracts\FeedParserInterface;

/**
 * Strategy parser for Twtxt flat-text feeds.
 */
class TwtxtParser implements FeedParserInterface
{
    public function getFormat(): string
    {
        return 'twtxt';
    }

    public function supports(string $content): bool
    {
        $content = trim($content);
        return str_starts_with($content, '# nick')
            || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T/m', $content) === 1;
    }

    public function parse(string $content, string $feedUrl): array
    {
        $lines = explode("\n", $content);
        $authorName = 'Unknown';
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '# nick')) {
                $parts = explode('=', $line);
                if (count($parts) === 2) {
                    $authorName = trim($parts[1]);
                }
            } elseif (preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|[+-][0-9]{2}:[0-9]{2}))\s+(.*)$/', $line, $matches)) {
                $published = strtotime($matches[1]) ?: time();
                $text = $matches[2];
                $id = md5($feedUrl . $published . $text);

                $items[] = [
                    'uid' => $id,
                    'url' => $feedUrl,
                    'title' => null,
                    'content' => $text,
                    'published_at' => $published,
                    'author' => ['name' => $authorName],
                ];
            }
        }

        return $items;
    }
}
