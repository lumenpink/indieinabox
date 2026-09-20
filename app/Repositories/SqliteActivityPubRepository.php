<?php

declare(strict_types=1);

namespace Indieinabox\Repositories;

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Repositories\Contracts\ActivityPubRepositoryInterface;
use PDO;

/**
 * SQLite implementation of ActivityPubRepositoryInterface.
 */
class SqliteActivityPubRepository implements ActivityPubRepositoryInterface
{
    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function getDb(): PDO
    {
        if ($this->db !== null) {
            return $this->db;
        }
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        return $container && $container->has(PDO::class) ? $container->get(PDO::class) : Database::getDb();
    }

    #[\Override]
    public function addFollower(string $actorUrl, string $inboxUrl, ?string $sharedInboxUrl = null): bool
    {
        $stmt = $this->getDb()->prepare(
            'INSERT OR REPLACE INTO activitypub_followers (actor_url, inbox_url, shared_inbox_url) VALUES (?, ?, ?)'
        );
        return $stmt->execute([$actorUrl, $inboxUrl, $sharedInboxUrl]);
    }

    #[\Override]
    public function removeFollower(string $actorUrl): bool
    {
        $stmt = $this->getDb()->prepare('DELETE FROM activitypub_followers WHERE actor_url = ?');
        return $stmt->execute([$actorUrl]);
    }

    #[\Override]
    public function isFollower(string $actorUrl): bool
    {
        $stmt = $this->getDb()->prepare('SELECT COUNT(*) FROM activitypub_followers WHERE actor_url = ?');
        $stmt->execute([$actorUrl]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    #[\Override]
    public function getFollowers(): array
    {
        $stmt = $this->getDb()->query('SELECT actor_url, inbox_url, shared_inbox_url FROM activitypub_followers');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    #[\Override]
    public function getDistinctInboxes(): array
    {
        $stmt = $this->getDb()->query(
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

    #[\Override]
    public function enqueueOutbox(string $payloadJson, string $targetInbox, ?int $createdAt = null): int
    {
        $stmt = $this->getDb()->prepare(
            "INSERT INTO activitypub_outbox (payload_json, target_inbox, status, created_at) VALUES (?, ?, 'pending', ?)"
        );
        $stmt->execute([$payloadJson, $targetInbox, $createdAt ?? time()]);
        return (int) $this->getDb()->lastInsertId();
    }

    #[\Override]
    public function getPendingOutbox(int $limit = 50): array
    {
        $stmt = $this->getDb()->prepare(
            "SELECT id, payload_json, target_inbox FROM activitypub_outbox WHERE status = 'pending' LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    #[\Override]
    public function updateOutboxStatus(int $id, string $status): bool
    {
        $stmt = $this->getDb()->prepare('UPDATE activitypub_outbox SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    #[\Override]
    public function pruneOutbox(int $olderThanTimestamp): int
    {
        $stmt = $this->getDb()->prepare("DELETE FROM activitypub_outbox WHERE status IN ('sent', 'failed') AND created_at < ?");
        $stmt->execute([$olderThanTimestamp]);
        return $stmt->rowCount();
    }
}
