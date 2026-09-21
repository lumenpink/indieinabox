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

    #[\Override]
    public function getChannels(): array
    {
        $stmt = $this->getDb()->query('SELECT uid, name FROM microsub_channels');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    #[\Override]
    public function createChannel(string $uid, string $name): bool
    {
        $stmt = $this->getDb()->prepare('INSERT INTO microsub_channels (uid, name) VALUES (:uid, :name)');
        $stmt->bindValue(':uid', $uid, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        return $stmt->execute();
    }

    #[\Override]
    public function deleteChannel(string $uid): bool
    {
        $stmt = $this->getDb()->prepare('DELETE FROM microsub_channels WHERE uid = :uid');
        $stmt->bindValue(':uid', $uid, PDO::PARAM_STR);
        $stmt->execute();

        $stmtSubs = $this->getDb()->prepare('DELETE FROM microsub_subscriptions WHERE channel_uid = :uid');
        $stmtSubs->bindValue(':uid', $uid, PDO::PARAM_STR);
        return $stmtSubs->execute();
    }

    #[\Override]
    public function getSubscriptions(string $channelUid): array
    {
        $stmt = $this->getDb()->prepare('SELECT url, type, name, photo FROM microsub_subscriptions WHERE channel_uid = :channel');
        $stmt->bindValue(':channel', $channelUid, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    #[\Override]
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

    #[\Override]
    public function getSubscriptionType(string $channelUid, string $url): ?string
    {
        $stmt = $this->getDb()->prepare('SELECT type FROM microsub_subscriptions WHERE channel_uid = :channel AND url = :url LIMIT 1');
        $stmt->bindValue(':channel', $channelUid, PDO::PARAM_STR);
        $stmt->bindValue(':url', $url, PDO::PARAM_STR);
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val !== false ? (string) $val : null;
    }

    #[\Override]
    public function removeSubscription(string $channelUid, string $url): bool
    {
        $stmt = $this->getDb()->prepare('DELETE FROM microsub_subscriptions WHERE channel_uid = :channel AND url = :url');
        $stmt->bindValue(':channel', $channelUid, PDO::PARAM_STR);
        $stmt->bindValue(':url', $url, PDO::PARAM_STR);
        return $stmt->execute();
    }

    #[\Override]
    public function countSubscriptionsByUrl(string $url): int
    {
        $stmt = $this->getDb()->prepare('SELECT COUNT(*) FROM microsub_subscriptions WHERE url = :url');
        $stmt->bindValue(':url', $url, PDO::PARAM_STR);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    private function ensureFiltersTable(): void
    {
        $this->getDb()->exec(
            'CREATE TABLE IF NOT EXISTS microsub_filters (' .
            'id INTEGER PRIMARY KEY AUTOINCREMENT, ' .
            'filter_type TEXT NOT NULL, ' .
            'content TEXT NOT NULL, ' .
            'action TEXT NOT NULL DEFAULT \'mute\', ' .
            'channel_uid TEXT DEFAULT \'*\', ' .
            'created_at INTEGER NOT NULL)'
        );
    }

    #[\Override]
    public function getFilters(?string $channelUid = null): array
    {
        $this->ensureFiltersTable();

        if ($channelUid === null) {
            $stmt = $this->getDb()->query('SELECT * FROM microsub_filters ORDER BY id DESC');
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        }

        $stmt = $this->getDb()->prepare(
            'SELECT * FROM microsub_filters WHERE channel_uid = :channel OR channel_uid = \'*\' ORDER BY id DESC'
        );
        $stmt->bindValue(':channel', $channelUid, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    #[\Override]
    public function addFilter(string $filterType, string $content, string $action = 'mute', string $channelUid = '*'): int
    {
        $this->ensureFiltersTable();

        $allowedTypes = ['keyword', 'author', 'domain'];
        $type = in_array($filterType, $allowedTypes, true) ? $filterType : 'keyword';

        $allowedActions = ['mute', 'auto_archive', 'drop'];
        $act = in_array($action, $allowedActions, true) ? $action : 'mute';

        $contentTrimmed = trim($content);
        $channel = trim($channelUid) !== '' ? trim($channelUid) : '*';

        $stmt = $this->getDb()->prepare(
            'INSERT INTO microsub_filters (filter_type, content, action, channel_uid, created_at) ' .
            'VALUES (:type, :content, :action, :channel, :created_at)'
        );
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':content', $contentTrimmed, PDO::PARAM_STR);
        $stmt->bindValue(':action', $act, PDO::PARAM_STR);
        $stmt->bindValue(':channel', $channel, PDO::PARAM_STR);
        $stmt->bindValue(':created_at', time(), PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->getDb()->lastInsertId();
    }

    #[\Override]
    public function deleteFilter(int $id): bool
    {
        $this->ensureFiltersTable();

        $stmt = $this->getDb()->prepare('DELETE FROM microsub_filters WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
