<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use PDO;

/**
 * Service managing federated follower relationships and inbox distribution targets.
 */
class FollowService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? (Container::getInstance()->has(PDO::class) ? Container::getInstance()->get(PDO::class) : Database::getDb());
    }

    /**
     * Records or updates an active remote follower.
     */
    public function addFollower(string $actorUrl, string $inboxUrl, ?string $sharedInboxUrl = null): bool
    {
        $stmt = $this->db->prepare(
            'INSERT OR REPLACE INTO activitypub_followers (actor_url, inbox_url, shared_inbox_url) VALUES (?, ?, ?)'
        );
        return $stmt->execute([$actorUrl, $inboxUrl, $sharedInboxUrl]);
    }

    /**
     * Removes a follower upon receiving an Unfollow activity.
     */
    public function removeFollower(string $actorUrl): bool
    {
        $stmt = $this->db->prepare('DELETE FROM activitypub_followers WHERE actor_url = ?');
        return $stmt->execute([$actorUrl]);
    }

    /**
     * Checks if the given remote actor is a current follower.
     */
    public function isFollower(string $actorUrl): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM activitypub_followers WHERE actor_url = ?');
        $stmt->execute([$actorUrl]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /**
     * Retrieves all follower records.
     *
     * @return array<int, array{actor_url: string, inbox_url: string, shared_inbox_url: ?string}>
     */
    public function getFollowers(): array
    {
        $stmt = $this->db->query('SELECT actor_url, inbox_url, shared_inbox_url FROM activitypub_followers');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Returns deduplicated inbox endpoints (prioritizing sharedInboxes) for fan-out broadcasting.
     *
     * @return array<int, string>
     */
    public function getDistinctInboxes(): array
    {
        $stmt = $this->db->query(
            'SELECT DISTINCT COALESCE(NULLIF(shared_inbox_url, ""), inbox_url) as inbox FROM activitypub_followers WHERE inbox_url != ""'
        );
        if (!$stmt) {
            return [];
        }

        $inboxes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['inbox'])) {
                $inboxes[] = (string) $row['inbox'];
            }
        }
        return $inboxes;
    }
}
