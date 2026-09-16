<?php

declare(strict_types=1);

namespace Indieinabox\Micropub;

use Indieinabox\Commands\CreatePostCommand;
use Indieinabox\Commands\Handlers\CreatePostCommandHandler;
use Indieinabox\Repositories\Contracts\ContentRepositoryInterface;
use Indieinabox\Site\Site;

/**
 * Class PostCreator
 *
 * Handles creation of new IndieWeb posts from Micropub input data,
 * post type discovery, frontmatter generation, and publication queuing.
 */
class PostCreator
{
    /**
     * Map of IndieWeb property keys to post kinds.
     */
    public const INDIEWEB_PROPERTIES = [
        'rsvp' => 'rsvp',
        'in-reply-to' => 'reply',
        'repost-of' => 'repost',
        'like-of' => 'like',
        'bookmark-of' => 'bookmark',
        'watch-of' => 'watch',
        'read-of' => 'read',
        'listen-of' => 'listen',
        'video' => 'video',
        'audio' => 'audio',
        'checkin' => 'checkin',
    ];

    /**
     * Creates a new post on disk from Micropub input and enqueues federation events.
     *
     * @param Site $site Global site configuration.
     * @param array<string, mixed> $input Form or JSON input payload.
     * @return array{status: int, headers: array<string, string>, post_url: string, file_path: string, kind: string, slug: string}
     */
    public static function create(
        Site $site,
        array $input,
        ?\Indieinabox\Repositories\Contracts\ContentRepositoryInterface $contentRepo = null
    ): array {
        $handler = new \Indieinabox\Commands\Handlers\CreatePostCommandHandler($contentRepo);
        return $handler->handle(new \Indieinabox\Commands\CreatePostCommand($site, $input));
    }

    /**
     * Discovers the post kind based on IndieWeb properties, photos, and title.
     *
     * @param array<string, mixed> $input
     * @param array<int, mixed> $photos
     * @return string
     */
    public static function discoverPostType(array $input, array $photos = []): string
    {
        foreach (self::INDIEWEB_PROPERTIES as $prop => $mappedKind) {
            if (!empty($input[$prop])) {
                return $mappedKind;
            }
        }

        if (!empty($photos)) {
            return 'photo';
        }

        if (!empty($input['name'])) {
            return 'article';
        }

        return 'note';
    }

    /**
     * Converts a string into a URL-friendly slug.
     *
     * @param string $text
     * @return string
     */
    public static function slugify(string $text): string
    {
        $text = (string) preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = (string) iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = (string) preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = (string) preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        return $text === '' ? 'n-a' : $text;
    }
}
