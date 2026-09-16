<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Core\Database;
use PDO;

/**
 * Service managing archived link snapshots, URL alias resolutions, and forced snapshot queueing.
 */
class ArchiveService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getDb();
    }

    /**
     * Resolves potential archive aliases to find the canonical target URL.
     */
    public function resolveAlias(string $url): string
    {
        $stmt = $this->db->prepare("SELECT target_url FROM archive_aliases WHERE alias_url = ?");
        $stmt->execute([$url]);
        if ($row = $stmt->fetch()) {
            return (string) $row['target_url'];
        }

        return $url;
    }

    /**
     * Finds the closest archived link snapshot by timestamp.
     *
     * @return array<string, mixed>|null
     */
    public function findSnapshot(string $url, ?int $timestamp = null): ?array
    {
        $targetUrl = $this->resolveAlias($url);
        $normUrl = rtrim(strtolower($targetUrl), '/');
        $ts = $timestamp ?? time();

        $stmt = $this->db->prepare(
            "SELECT * FROM archived_links WHERE url = ? ORDER BY ABS(timestamp - ?) ASC LIMIT 1"
        );
        $stmt->execute([$normUrl, $ts]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Enqueues a URL for forced archive capture.
     */
    public function queueForceArchive(string $url): bool
    {
        $targetUrl = $this->resolveAlias($url);
        $normUrl = rtrim(strtolower($targetUrl), '/');

        $stmt = $this->db->prepare("INSERT INTO archive_queue (url, force_archive) VALUES (?, 1)");
        return $stmt->execute([$normUrl]);
    }
}
