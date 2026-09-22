<?php

declare(strict_types=1);

namespace Indieinabox\ActivityPub;

use Indieinabox\Core\Database;

/**
 * Class ActivityBuilder
 *
 * Builds ActivityStreams 2.0 objects and activities (Note, Article, Create, Accept, etc.).
 */
class ActivityBuilder
{
    /**
     * Builds an ActivityStreams object (Note or Article) for a given page/post.
     *
     * @param string $objectId Unique IRI of the object.
     * @param string $actorId Actor IRI.
     * @param string $fqdn Fully qualified domain name of the site.
     * @param string $content HTML or text content of the post.
     * @param ?string $name Optional title (promotes Note to Article).
     * @param array<string, mixed> $metadata Extra metadata (photos, syndication, ratings, read_of, reply, etc.).
     * @return array<string, mixed>
     */
    public static function buildObjectForPageArray(
        string $objectId,
        string $actorId,
        string $fqdn,
        string $content,
        ?string $name,
        array $metadata = []
    ): array {
        $object = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1'
            ],
            'id' => $objectId,
            'type' => 'Note',
            'published' => gmdate('Y-m-d\TH:i:s\Z'),
            'url' => $objectId,
            'attributedTo' => $actorId,
            'content' => $content,
            'to' => ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => [$fqdn . '/followers']
        ];

        if ($name) {
            $object['type'] = 'Article';
            $object['name'] = $name;
        }

        if (isset($metadata['read_of'])) {
            $object['type'] = 'Article';
            $object['inReplyToBook'] = $metadata['read_of'];

            if (isset($metadata['rating'])) {
                $object['rating'] = (int) $metadata['rating'];
            } elseif (isset($metadata['p_rating'])) {
                $object['rating'] = (int) $metadata['p_rating'];
            }

            if (isset($metadata['read_status'])) {
                $object['readingStatus'] = $metadata['read_status'];
            }
        }

        if (isset($metadata['reply'])) {
            $object['inReplyTo'] = $metadata['reply'];
        }

        // Support Misskey Quote Renotes (_misskey_quote / quoteUrl)
        if (isset($metadata['quote']) || isset($metadata['repost_of']) || isset($metadata['_misskey_quote']) || isset($metadata['quoteUrl'])) {
            $quoteTarget = (string)($metadata['quote'] ?? ($metadata['_misskey_quote'] ?? ($metadata['quoteUrl'] ?? ($metadata['repost_of'] ?? ''))));
            if ($quoteTarget !== '') {
                $object['_misskey_quote'] = $quoteTarget;
                $object['quoteUrl'] = $quoteTarget;
            }
        }

        $to = ['https://www.w3.org/ns/activitystreams#Public'];
        $cc = [$fqdn . '/followers'];

        // Add Attachments for Pixelfed/Mastodon (Images, Galleries, Reels/Videos)
        $attachments = [];
        $photos = $metadata['photo'] ?? $metadata['photos'] ?? [];
        if (!is_array($photos)) {
            $photos = [$photos];
        }
        foreach ($photos as $photo) {
            $photoUrl = '';
            $altName = 'Image';
            $focalPoint = null;
            $blurhash = null;
            $width = null;
            $height = null;

            if (is_array($photo)) {
                $photoUrl = (string)($photo['url'] ?? $photo['value'] ?? '');
                $altName = (string)($photo['alt'] ?? $photo['name'] ?? 'Image');
                $focalPoint = $photo['focal_point'] ?? $photo['focalPoint'] ?? null;
                $blurhash = isset($photo['blurhash']) ? (string)$photo['blurhash'] : null;
                $width = isset($photo['width']) ? (int)$photo['width'] : null;
                $height = isset($photo['height']) ? (int)$photo['height'] : null;
            } else {
                $photoUrl = (string)$photo;
            }

            if ($photoUrl === '') {
                continue;
            }

            $resolvedUrl = filter_var($photoUrl, FILTER_VALIDATE_URL) ? $photoUrl : rtrim($fqdn, '/') . '/' . ltrim($photoUrl, '/');
            $ext = strtolower(pathinfo((string)parse_url($resolvedUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
            $mediaType = 'image/jpeg';
            if ($ext === 'png') {
                $mediaType = 'image/png';
            } elseif ($ext === 'gif') {
                $mediaType = 'image/gif';
            } elseif ($ext === 'webp') {
                $mediaType = 'image/webp';
            } elseif ($ext === 'avif') {
                $mediaType = 'image/avif';
            }

            $attachment = [
                'type' => 'Image',
                'mediaType' => $mediaType,
                'url' => $resolvedUrl,
                'name' => $altName
            ];

            if ($blurhash !== null && $blurhash !== '') {
                $attachment['blurhash'] = $blurhash;
            }

            if (is_array($focalPoint) && count($focalPoint) >= 2) {
                $attachment['focalPoint'] = array_values(array_map('floatval', $focalPoint));
            }

            if ($width !== null && $height !== null) {
                $attachment['width'] = $width;
                $attachment['height'] = $height;
            }

            $attachments[] = $attachment;
        }

        // Support video / reel attachments (Pixelfed Reels / Mastodon Videos)
        $videoMedia = $metadata['video'] ?? $metadata['reel'] ?? $metadata['reels'] ?? null;
        if ($videoMedia) {
            $videos = is_array($videoMedia) && !isset($videoMedia['url']) ? $videoMedia : [$videoMedia];
            foreach ($videos as $vid) {
                $vidUrl = is_array($vid) ? (string)($vid['url'] ?? '') : (string)$vid;
                if ($vidUrl === '') {
                    continue;
                }
                $resolvedVidUrl = filter_var($vidUrl, FILTER_VALIDATE_URL) ? $vidUrl : rtrim($fqdn, '/') . '/' . ltrim($vidUrl, '/');
                $ext = strtolower(pathinfo((string)parse_url($resolvedVidUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
                $mediaType = $ext === 'webm' ? 'video/webm' : 'video/mp4';
                $vidAttachment = [
                    'type' => 'Video',
                    'mediaType' => is_array($vid) && !empty($vid['media_type']) ? (string)$vid['media_type'] : $mediaType,
                    'url' => $resolvedVidUrl,
                    'name' => is_array($vid) ? (string)($vid['alt'] ?? 'Video') : 'Video'
                ];
                if (is_array($vid) && !empty($vid['poster'])) {
                    $posterUrl = (string)$vid['poster'];
                    $resolvedPoster = filter_var($posterUrl, FILTER_VALIDATE_URL) ? $posterUrl : rtrim($fqdn, '/') . '/' . ltrim($posterUrl, '/');
                    $vidAttachment['icon'] = [
                        'type' => 'Image',
                        'url' => $resolvedPoster
                    ];
                }
                $attachments[] = $vidAttachment;
            }
        }

        if (!empty($attachments)) {
            $object['attachment'] = $attachments;
        }

        // Add Custom Emojis tags
        $tags = [];
        if (preg_match_all('/:([a-zA-Z0-9_]+):/', $content, $matches)) {
            $uniqueEmojis = array_unique($matches[1]);
            $config = Database::getAllSettings();
            $contentDirName = $config['contentdir'] ?? 'content';
            $baseDir = Database::$dataDir !== '' ? Database::$dataDir : dirname(__DIR__, 2);
            $emojiDir = $baseDir . '/' . $contentDirName . '/media/emojis';

            if (is_dir($emojiDir)) {
                foreach ($uniqueEmojis as $shortcode) {
                    foreach (['png', 'gif', 'jpg', 'webp'] as $ext) {
                        if (file_exists($emojiDir . '/' . $shortcode . '.' . $ext)) {
                            $tags[] = [
                                'type' => 'Emoji',
                                'id' => rtrim($fqdn, '/') . '/media/emojis/' . $shortcode . '.' . $ext,
                                'name' => ':' . $shortcode . ':',
                                'icon' => [
                                    'type' => 'Image',
                                    'mediaType' => 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
                                    'url' => rtrim($fqdn, '/') . '/media/emojis/' . $shortcode . '.' . $ext
                                ]
                            ];
                            break;
                        }
                    }
                }
            }
        }
        if (!empty($tags)) {
            $object['tag'] = $tags;
        }

        $syn = $metadata['syndicate_to'] ?? $metadata['mp_syndicate_to'] ?? null;
        if ($syn) {
            $syndicates = is_array($syn) ? $syn : [$syn];
            foreach ($syndicates as $target) {
                if (filter_var($target, FILTER_VALIDATE_URL)) {
                    $to[] = $target;
                }
            }
        }

        $object['to'] = $to;
        $object['cc'] = $cc;

        return $object;
    }

    /**
     * Builds a Create activity for an ActivityStreams object.
     *
     * @param string $activityId Unique IRI of the activity.
     * @param string $actorId Actor IRI.
     * @param array<string, mixed> $object The inner object payload.
     * @return array<string, mixed>
     */
    public static function buildCreateActivity(string $activityId, string $actorId, array $object): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => 'Create',
            'actor' => $actorId,
            'published' => gmdate('Y-m-d\TH:i:s\Z'),
            'to' => $object['to'] ?? [],
            'cc' => $object['cc'] ?? [],
            'object' => $object
        ];
    }

    /**
     * Builds an Accept activity for a Follow request.
     *
     * @param string $acceptId Unique IRI of the accept activity.
     * @param string $actorId Actor IRI.
     * @param array<string, mixed> $followActivity The received follow activity payload.
     * @return array<string, mixed>
     */
    public static function buildAcceptActivity(string $acceptId, string $actorId, array $followActivity): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $acceptId,
            'type' => 'Accept',
            'actor' => $actorId,
            'object' => $followActivity
        ];
    }

    /**
     * Builds a Like or Announce (Repost) activity.
     *
     * @param string $activityId Unique IRI of the activity.
     * @param string $type Activity type ('Like' or 'Announce').
     * @param string $actorId Actor IRI.
     * @param string $targetUri Remote object URI.
     * @return array<string, mixed>
     */
    public static function buildInteractionActivity(string $activityId, string $type, string $actorId, string $targetUri): array
    {
        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $activityId,
            'type' => $type,
            'actor' => $actorId,
            'object' => $targetUri
        ];
    }
}
