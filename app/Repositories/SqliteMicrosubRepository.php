<?php

declare(strict_types=1);

namespace Indieinabox\Repositories;

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Repositories\Contracts\MicrosubRepositoryInterface;
use PDO;

/**
 * SQLite implementation of MicrosubRepositoryInterface.
 */
class SqliteMicrosubRepository implements MicrosubRepositoryInterface
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

    public function getChannels(): array
    {
        $stmt = $this->getDb()->query('SELECT uid, name FROM microsub_channels');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function createChannel(string $uid, string $name): bool
    {
        $stmt = $this->getDb()->prepare('INSERT INTO microsub_channels (uid, name) VALUES (:uid, :name)');
        $stmt->bindValue(':uid', $uid, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function deleteChannel(string $uid): bool
    {
        $stmt = $this->getDb()->prepare('DELETE FROM microsub_channels WHERE uid = :uid');
        $stmt->bindValue(':uid', $uid, PDO::PARAM_STR);
        $stmt->execute();

        $stmtSubs = $this->getDb()->prepare('DELETE FROM microsub_subscriptions WHERE channel_uid = :uid');
        $stmtSubs->bindValue(':uid', $uid, PDO::PARAM_STR);
        return $stmtSubs->execute();
    }

    public function getSubscriptions(string $channelUid): array
    {
        $stmt = $this->getDb()->prepare('SELECT url, type, name, photo FROM microsub_subscriptions WHERE channel_uid = :channel');
        $stmt->bindValue(':channel', $channelUid, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addSubscription(
        string $channelUid,
        string $url,
        string $type = 'feed',
        string $name = '',
        string $photo = ''
    ): bool {
        $stmt = $this->getDb()->prepare(
            'INSERT INTO microsub_subscriptions (channel_uid, url, type, name, photo) VALUES (:channel, :url, :type, :name, :photo)'
        );
        $stmt->bindValue(':channel', $channelUid, PDO::PARAM_STR);
        $stmt->bindValue(':url', $url, PDO::PARAM_STR);
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':photo', $photo, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function getSubscriptionType(string $channelUid, string $url): ?string
    {
        $stmt = $this->getDb()->prepare('SELECT type FROM microsub_subscriptions WHERE channel_uid = :channel AND url = :url LIMIT 1');
        $stmt->bindValue(':channel', $channelUid, PDO::PARAM_STR);
        $stmt->bindValue(':url', $url, PDO::PARAM_STR);
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val !== false ? (string) $val : null;
    }

    public function removeSubscription(string $channelUid, string $url): bool
    {
        $stmt = $this->getDb()->prepare('DELETE FROM microsub_subscriptions WHERE channel_uid = :channel AND url = :url');
        $stmt->bindValue(':channel', $channelUid, PDO::PARAM_STR);
        $stmt->bindValue(':url', $url, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function countSubscriptionsByUrl(string $url): int
    {
        $stmt = $this->getDb()->prepare('SELECT COUNT(*) FROM microsub_subscriptions WHERE url = :url');
        $stmt->bindValue(':url', $url, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
