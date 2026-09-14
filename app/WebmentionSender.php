<?php

declare(strict_types=1);

namespace Indieinabox;

use PDO;
use Indieinabox\Webmention\LinkExtractor;

/**
 * Class WebmentionSender
 *
 * Scans newly published or modified content for outbound mentions and queues them for delivery.
 */
class WebmentionSender
{
    /**
     * Extracts outgoing links and queues them for webmention sending.
     *
     * @param string $sourceUrl The URL of the post we just created.
     * @param array<string, mixed> $frontmatter The frontmatter of the post.
     * @param string $content The Markdown or HTML content of the post.
     * @param ?PDO $db Optional database connection.
     * @return void
     */
    public static function queueOutgoingWebmentions(
        string $sourceUrl,
        array $frontmatter,
        string $content,
        ?PDO $db = null
    ): void {
        $settings = Database::getAllSettings();
        if (empty($settings['webmention_enabled'])) {
            return;
        }

        $links = LinkExtractor::extractLinks($sourceUrl, $frontmatter, $content);
        if (empty($links)) {
            return;
        }

        $database = $db ?? Database::getDb();
        $stmt = $database->prepare(
            'INSERT INTO outgoing_webmentions (source_url, target_url, created_at) VALUES (:source, :target, :time)'
        );

        $now = time();
        foreach ($links as $targetUrl) {
            // Check if we already queued this exact pair recently to prevent spam
            $check = $database->prepare(
                'SELECT id FROM outgoing_webmentions WHERE source_url = ? AND target_url = ?'
            );
            $check->execute([$sourceUrl, $targetUrl]);
            if ($check->fetch()) {
                continue;
            }

            $stmt->bindValue(':source', $sourceUrl);
            $stmt->bindValue(':target', $targetUrl);
            $stmt->bindValue(':time', $now);
            $stmt->execute();
        }
    }
}
