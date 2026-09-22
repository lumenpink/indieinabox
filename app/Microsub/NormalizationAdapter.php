<?php

declare(strict_types=1);

namespace Indieinabox\Microsub;

use Indieinabox\Support\TextParser;

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

        // Attachments extraction (Pixelfed galleries, images, audio, video)
        $rawAttachments = $json['attachment'] ?? [];
        if (!is_array($rawAttachments)) {
            $rawAttachments = [$rawAttachments];
        }
        // Also support orderedItems collection or item if present
        if (isset($json['attachment']['orderedItems']) && is_array($json['attachment']['orderedItems'])) {
            $rawAttachments = $json['attachment']['orderedItems'];
        }

        $photos = [];
        $reel = null;

        foreach ($rawAttachments as $att) {
            if (!is_array($att)) {
                if (is_string($att) && filter_var($att, FILTER_VALIDATE_URL)) {
                    $photos[] = [
                        'url' => $att,
                        'alt' => '',
                    ];
                }
                continue;
            }

            $type = (string)($att['type'] ?? '');
            $mediaType = (string)($att['mediaType'] ?? ($att['mimeType'] ?? ''));
            $url = (string)($att['url'] ?? '');
            if (is_array($att['url'] ?? null)) {
                $url = (string)($att['url']['href'] ?? ($att['url']['url'] ?? ''));
            }

            if ($url === '') {
                continue;
            }

            $name = (string)($att['name'] ?? ($att['summary'] ?? ''));
            $blurhash = (string)($att['blurhash'] ?? '');
            $focalPoint = null;
            if (isset($att['focalPoint']) && is_array($att['focalPoint'])) {
                $focalPoint = array_values(array_map('floatval', $att['focalPoint']));
            } elseif (isset($att['focal_point']) && is_array($att['focal_point'])) {
                $focalPoint = array_values(array_map('floatval', $att['focal_point']));
            }

            $width = isset($att['width']) ? (int)$att['width'] : null;
            $height = isset($att['height']) ? (int)$att['height'] : null;

            $isImage = str_starts_with($mediaType, 'image/') || in_array($type, ['Image', 'Document'], true) || preg_match('/\.(jpe?g|png|gif|webp|avif)$/i', $url);
            $isVideo = str_starts_with($mediaType, 'video/') || in_array($type, ['Video'], true) || preg_match('/\.(mp4|webm|mov)$/i', $url);

            if ($isImage) {
                $photoItem = [
                    'url' => $url,
                    'alt' => $name,
                ];
                if ($blurhash !== '') {
                    $photoItem['blurhash'] = $blurhash;
                }
                if ($focalPoint !== null && count($focalPoint) >= 2) {
                    $photoItem['focal_point'] = $focalPoint;
                }
                if ($width !== null && $height !== null) {
                    $photoItem['width'] = $width;
                    $photoItem['height'] = $height;
                    $photoItem['aspect_ratio'] = $height > 0 ? round($width / $height, 4) : 1.0;
                }
                $photos[] = $photoItem;
            } elseif ($isVideo && $reel === null) {
                // Check if marked as reel / short loop / story or portrait aspect ratio
                $isReel = (!empty($att['is_reel']) || !empty($json['is_reel']) || !empty($json['pixelfed:reel'])
                    || (is_array($json['tag'] ?? null) && in_array('reel', array_column($json['tag'], 'name'), true))
                    || ($width !== null && $height !== null && $height > $width));

                $poster = null;
                if (isset($att['icon']['url'])) {
                    $poster = (string)$att['icon']['url'];
                } elseif (isset($att['preview']['url'])) {
                    $poster = (string)$att['preview']['url'];
                }

                $reel = [
                    'url' => $url,
                    'media_type' => $mediaType ?: 'video/mp4',
                    'poster' => $poster,
                    'is_reel' => $isReel,
                    'alt' => $name,
                    'width' => $width,
                    'height' => $height,
                ];
            }
        }

        if (!empty($photos)) {
            $entry->photos = $photos;
        }

        if ($reel !== null) {
            $entry->reels = $reel;
        }

        // Misskey Quote Post / Renote with commentary (_misskey_quote, quoteUrl, or quoting object)
        $quoteUrl = $json['_misskey_quote'] ?? $json['quoteUrl'] ?? $json['quote_url'] ?? null;
        if ($quoteUrl !== null && is_string($quoteUrl) && filter_var($quoteUrl, FILTER_VALIDATE_URL)) {
            $quoteContent = '';
            $quoteAuthor = '';
            if (isset($json['_misskey_quote_object']) && is_array($json['_misskey_quote_object'])) {
                $quoteContent = (string)($json['_misskey_quote_object']['content'] ?? '');
                $quoteAuthor = (string)($json['_misskey_quote_object']['attributedTo'] ?? '');
            }
            $entry->quote = [
                'url' => $quoteUrl,
                'content' => $quoteContent,
                'author' => $quoteAuthor,
            ];
            $entry->capabilities[] = 'quote';
        }

        // Misskey Custom Emoji Reaction (_misskey_reaction or reaction tag)
        if (!empty($json['_misskey_reaction'])) {
            $entry->reaction = (string)$json['_misskey_reaction'];
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
        
        $entry->content['html'] = \Indieinabox\Twtxt\TwtxtManager::formatMessageToHtml($text);

        $entry->category = TextParser::extractHashtags($text);

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
        
        $entry->category = TextParser::extractHashtags($entry->content['text']);

        $entry->author = [
            'type' => 'card',
            'name' => $authorName
        ];

        // Webmention Discovery Cache Lookup
        $parsedUrl = parse_url($feedUrl);
        $domain = $parsedUrl['host'] ?? 'unknown';
        $entry->originServer = $domain;

        if ($domain !== 'unknown') {
            $db = \Indieinabox\Core\Database::getDb();
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
