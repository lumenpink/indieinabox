<?php

declare(strict_types=1);

namespace Indieinabox\Microsub;

/**
 * Normalization Adapter
 * 
 * Takes parsed data from various networks (ActivityPub, Twtxt, RSS)
 * and normalizes it into a Universal Post Object (ExtendedEntry).
 */
class NormalizationAdapter
{
    /**
     * Creates an ExtendedEntry from an ActivityPub JSON object.
     */
    public static function fromActivityPub(array $json, string $htmlContent): ExtendedEntry
    {
        $entry = new ExtendedEntry();
        $entry->network = 'activitypub';
        
        $entry->uid = $json['id'] ?? md5(json_encode($json));
        $entry->url = $json['url'] ?? $entry->uid;
        
        if (isset($json['published'])) {
            $entry->published = date('c', strtotime($json['published']));
        } else {
            $entry->published = date('c');
        }

        $entry->content['html'] = $htmlContent;
        $entry->content['text'] = strip_tags($htmlContent);

        // Capability Constraints for AP
        $entry->capabilities = ['reply', 'like', 'repost'];

        // Origin tracking
        $parsedUrl = parse_url($entry->url);
        $entry->originServer = $parsedUrl['host'] ?? 'unknown';

        // Content Warning / Summary extraction
        if (!empty($json['summary'])) {
            $entry->contentWarning = $json['summary'];
        }

        // Poll extraction
        if (!empty($json['oneOf']) || !empty($json['anyOf'])) {
            $entry->capabilities[] = 'poll_vote';
            $options = [];
            $choices = !empty($json['oneOf']) ? $json['oneOf'] : $json['anyOf'];
            foreach ($choices as $choice) {
                if (isset($choice['name'])) {
                    $options[] = [
                        'title' => $choice['name'],
                        'votes' => $choice['replies']['totalItems'] ?? 0
                    ];
                }
            }
            if (count($options) > 0) {
                $entry->poll = [
                    'multiple_choice' => !empty($json['anyOf']),
                    'options' => $options
                ];
            }
        }

        return $entry;
    }

    /**
     * Creates an ExtendedEntry from Twtxt data.
     */
    public static function fromTwtxt(string $uid, string $url, string $text, int $timestamp, string $authorName): ExtendedEntry
    {
        $entry = new ExtendedEntry();
        $entry->network = 'twtxt';
        $entry->uid = $uid;
        $entry->url = $url;
        $entry->published = date('c', $timestamp);
        
        $entry->content['text'] = $text;
        
        $html = htmlspecialchars($text);
        $html = preg_replace('/(https?:\/\/[^\s]+)/', '<a href="$1">$1</a>', $html);
        $entry->content['html'] = $html;

        $entry->category = \Indieinabox\Helper::extractHashtags($text);

        $entry->author = [
            'type' => 'card',
            'name' => $authorName
        ];

        // Capability Constraints for Twtxt
        // Twtxt only supports reply natively (mentions), no likes or reposts in standard spec
        $entry->capabilities = ['reply'];

        $parsedUrl = parse_url($url);
        $entry->originServer = $parsedUrl['host'] ?? 'unknown';

        return $entry;
    }

    /**
     * Creates an ExtendedEntry from standard Feed items (RSS/Atom/JSONFeed).
     */
    public static function fromFeed(string $uid, string $url, string $htmlContent, int $timestamp, string $authorName, string $feedUrl): ExtendedEntry
    {
        $entry = new ExtendedEntry();
        $entry->network = 'rss';
        $entry->uid = $uid;
        $entry->url = $url;
        $entry->published = date('c', $timestamp);
        
        $entry->content['html'] = $htmlContent;
        $entry->content['text'] = strip_tags($htmlContent);
        
        $entry->category = \Indieinabox\Helper::extractHashtags($entry->content['text']);

        $entry->author = [
            'type' => 'card',
            'name' => $authorName
        ];

        // Webmention Discovery Cache Lookup
        $parsedUrl = parse_url($feedUrl);
        $domain = $parsedUrl['host'] ?? 'unknown';
        $entry->originServer = $domain;

        if ($domain !== 'unknown') {
            $db = \Indieinabox\Database::getDb();
            $stmt = $db->prepare('SELECT supports_webmention, last_checked FROM webmention_discovery_cache WHERE domain = ?');
            $stmt->execute([$domain]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                if ($row['last_checked'] > 0 && $row['supports_webmention'] == 1) {
                    $entry->capabilities = ['reply', 'like', 'repost'];
                } else {
                    $entry->capabilities = ['local_reply', 'local_like', 'repost'];
                }
            } else {
                // Unknown domain, queue for discovery and default to local interactions for now
                $stmtInsert = $db->prepare('INSERT INTO webmention_discovery_cache (domain, supports_webmention, last_checked) VALUES (?, 0, 0)');
                $stmtInsert->execute([$domain]);
                $entry->capabilities = ['local_reply', 'local_like', 'repost'];
            }
        } else {
            $entry->capabilities = ['local_reply', 'local_like', 'repost'];
        }

        return $entry;
    }
}
