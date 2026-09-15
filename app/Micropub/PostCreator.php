<?php

declare(strict_types=1);

namespace Indieinabox\Micropub;

use Indieinabox\Site;
use Indieinabox\Support\TextParser;
use Indieinabox\Database;

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
    public static function create(Site $site, array $input): array
    {
        $name = isset($input['name']) && $input['name'] !== '' ? (string) $input['name'] : null;
        $content = $input['content'] ?? '';
        if (is_array($content)) {
            $content = (string) ($content['html'] ?? ($content['value'] ?? ''));
        } else {
            $content = (string) $content;
        }

        $slug = (string) ($input['mp-slug'] ?? ($name !== null ? self::slugify($name) : date('dHis')));
        $lang = (string) ($input['mp-language'] ?? '');
        $category = $input['category'] ?? [];
        if (!is_array($category) && !empty($category)) {
            $category = [$category];
        }

        // Auto-extract hashtags from content
        $extractedTags = TextParser::extractHashtags($content);
        if (!empty($extractedTags)) {
            $category = array_unique(array_merge($category, $extractedTags));
        }

        // Photo uploads sent with the post
        $photos = [];
        if (isset($input['photo'])) {
            $photos = is_array($input['photo']) ? $input['photo'] : [$input['photo']];
        }

        // Post Type Discovery (W3C)
        $kind = self::discoverPostType($input, $photos);

        // Generate Frontmatter
        $frontmatter = [];
        if ($name !== null) {
            $frontmatter['title'] = $name;
        }
        $frontmatter['date'] = date('Y-m-d H:i:s');
        if (!empty($category)) {
            $frontmatter['tags'] = array_values($category);
        }

        foreach (array_keys(self::INDIEWEB_PROPERTIES) as $prop) {
            if (isset($input[$prop])) {
                $frontmatter[str_replace('-', '_', $prop)] = $input[$prop];
            }
        }

        $otherProps = ['read-status', 'rating', 'p-rating', 'syndicate-to', 'mp-syndicate-to'];
        foreach ($otherProps as $op) {
            if (isset($input[$op])) {
                $frontmatter[str_replace('-', '_', $op)] = $input[$op];
            }
        }

        $yaml = "---\n";
        foreach ($frontmatter as $k => $v) {
            if (is_array($v)) {
                $yaml .= "$k:\n";
                foreach ($v as $item) {
                    $yaml .= "  - $item\n";
                }
            } else {
                $yaml .= "$k: \"$v\"\n";
            }
        }
        $yaml .= "---\n\n";

        // Append photos to content if not already present
        foreach ($photos as $photo) {
            if (is_string($photo) && strpos($content, $photo) === false) {
                $yaml .= "![]($photo)\n\n";
            }
        }

        $yaml .= $content;

        // Determine directory path
        $contentDir = rtrim($site->paths->contentDir, DIRECTORY_SEPARATOR);
        $defaultLang = $site->localization->defaultLang ?? 'en';
        if ($lang !== '' && $lang !== $defaultLang) {
            $contentDir .= DIRECTORY_SEPARATOR . $lang;
        }

        $year = date('Y');
        $month = date('m');
        $dir = $contentDir . DIRECTORY_SEPARATOR . $kind . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $originalSlug = $slug;
        $counter = 1;
        while (file_exists($dir . DIRECTORY_SEPARATOR . $slug . '.md')) {
            if (is_numeric($originalSlug)) {
                $slug = (string) ((int) $originalSlug + $counter);
            } else {
                $slug = $originalSlug . '-' . $counter;
            }
            $counter++;
        }

        $filePath = $dir . DIRECTORY_SEPARATOR . $slug . '.md';
        file_put_contents($filePath, $yaml);

        // Queue site rebuild
        self::enqueueSiteBuild();

        // Build canonical URL
        $baseUrl = rtrim($site->fqdn ?? '', '/');
        $postUrl = $baseUrl . '/' . $kind . '/' . $year . '/' . $month . '/' . $slug . '.html';
        if ($lang !== '' && $lang !== $defaultLang) {
            $postUrl = $baseUrl . '/' . $lang . '/' . $kind . '/' . $year . '/' . $month . '/' . $slug . '.html';
        }

        // Queue ActivityPub outbox message via OutboxService
        if (class_exists('\\Indieinabox\\Services\\OutboxService')) {
            $actorId = $baseUrl . '/actor';
            $object = \Indieinabox\ActivityPub\ActivityBuilder::buildObjectForPageArray(
                $postUrl,
                $actorId,
                $baseUrl,
                $content,
                $name,
                $frontmatter
            );
            $createActivity = \Indieinabox\ActivityPub\ActivityBuilder::buildCreateActivity(
                $postUrl . '#activity',
                $actorId,
                $object
            );
            $outboxService = new \Indieinabox\Services\OutboxService();
            $outboxService->broadcastActivity($createActivity);
        }

        // Queue outgoing webmentions
        if (class_exists('\\Indieinabox\\WebmentionSender')) {
            \Indieinabox\WebmentionSender::queueOutgoingWebmentions($postUrl, $frontmatter, $content);
        }

        return [
            'status' => 202,
            'headers' => ['Location' => $postUrl],
            'post_url' => $postUrl,
            'file_path' => $filePath,
            'kind' => $kind,
            'slug' => $slug,
        ];
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

    /**
     * Enqueues an asynchronous site rebuild in the background queue.
     *
     * @return void
     */
    private static function enqueueSiteBuild(): void
    {
        if (!class_exists('\\Indieinabox\\ConfigHandler')) {
            return;
        }

        $db = Database::getDb();
        $stmt = $db->query("SELECT 1 FROM inbox_queue WHERE type = 'build_site'");
        if ($stmt && !$stmt->fetch()) {
            $insert = $db->prepare('INSERT INTO inbox_queue (type, payload_json, created_at) VALUES (?, ?, ?)');
            $insert->execute(['build_site', json_encode([]), time()]);
        }
    }
}
