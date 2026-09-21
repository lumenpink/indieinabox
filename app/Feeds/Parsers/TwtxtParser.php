<?php

declare(strict_types=1);

namespace Indieinabox\Feeds\Parsers;

use Indieinabox\Feeds\Contracts\FeedParserInterface;

/**
 * Strategy parser for Twtxt flat-text feeds.
 */
class TwtxtParser implements FeedParserInterface
{
    #[\Override]
    public function getFormat(): string
    {
        return 'twtxt';
    }

    #[\Override]
    public function supports(string $content): bool
    {
        $content = trim($content);
        return str_starts_with($content, '# nick')
            || str_starts_with($content, '# title')
            || str_starts_with($content, '# author')
            || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T/m', $content) === 1;
    }

    #[\Override]
    public function parse(string $content, string $feedUrl): array
    {
        $lines = explode("\n", $content);
        $authorName = 'Unknown';
        $feedTitle = null;
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#')) {
                if (preg_match('/^#\s*([a-zA-Z0-9_-]+)\s*=\s*(.*)$/', $line, $headerMatch)) {
                    $key = strtolower(trim($headerMatch[1]));
                    $val = trim($headerMatch[2]);
                    if ($key === 'nick' || $key === 'author') {
                        $authorName = $val;
                    } elseif ($key === 'title') {
                        $feedTitle = $val;
                    }
                }
                continue;
            }

            if (preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|[+-][0-9]{2}:[0-9]{2}))\s+(.*)$/', $line, $matches)) {
                $published = strtotime($matches[1]) ?: time();
                $text = $matches[2];
                $id = md5($feedUrl . $published . $text);

                $subject = null;
                if (preg_match('/(?<=^|\s)\(#([a-zA-Z0-9_\-\.\/]+)\)/u', $text, $subjMatch)) {
                    $subject = $subjMatch[1];
                }

                $attachments = \Indieinabox\Twtxt\TwtxtManager::extractMediaAttachments($text);

                $items[] = [
                    'uid' => $id,
                    'url' => $feedUrl,
                    'title' => $subject ?? $feedTitle,
                    'content' => $text,
                    'published_at' => $published,
                    'author' => ['name' => $authorName],
                    'attachments' => $attachments,
                    'metadata' => [
                        'subject' => $subject,
                        'feed_title' => $feedTitle,
                    ],
                ];
            }
        }

        return $items;
    }
}
