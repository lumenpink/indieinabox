<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\ActivityPub\ActivityBuilder;
use Indieinabox\Database;
use Indieinabox\Federation\FederationManager;
use Indieinabox\Site;
use PDO;

/**
 * Service orchestrating incoming federated activities, follow requests, and interaction ingestion.
 */
class InboxService
{
    private Site $site;
    private FederationManager $federationManager;
    private FollowService $followService;
    private OutboxService $outboxService;
    private PDO $db;

    public function __construct(
        Site $site,
        FederationManager $federationManager,
        FollowService $followService,
        OutboxService $outboxService,
        ?PDO $db = null
    ) {
        $this->site = $site;
        $this->federationManager = $federationManager;
        $this->followService = $followService;
        $this->outboxService = $outboxService;
        $this->db = $db ?? Database::getDb();
    }

    /**
     * Enqueues an incoming payload for asynchronous processing.
     *
     * @param string $type
     * @param array<string, mixed>|string $payload
     * @return int Inserted ID
     */
    public function enqueue(string $type, array|string $payload): int
    {
        $payloadJson = is_array($payload)
            ? (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $payload;

        $stmt = $this->db->prepare(
            'INSERT INTO inbox_queue (type, payload_json, created_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$type, $payloadJson, time()]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Synchronously processes a normalized incoming federated activity.
     *
     * @param array<string, mixed> $activity
     * @return bool
     */
    public function handleActivity(array $activity): bool
    {
        $type = $activity['type'] ?? '';
        $actor = $activity['actor'] ?? '';

        if (is_array($actor)) {
            $actor = $actor['id'] ?? '';
        }

        if (empty($type) || empty($actor)) {
            return false;
        }

        if ($type === 'Follow') {
            return $this->handleFollow($activity, (string) $actor);
        }

        if ($type === 'Undo') {
            $object = $activity['object'] ?? [];
            if (is_array($object) && ($object['type'] ?? '') === 'Follow') {
                return $this->followService->removeFollower((string) $actor);
            }
        }

        return true;
    }

    /**
     * Handles a Follow activity by recording the follower and sending an Accept activity.
     *
     * @param array<string, mixed> $activity
     * @param string $actorUrl
     * @return bool
     */
    private function handleFollow(array $activity, string $actorUrl): bool
    {
        $inboxUrl = $activity['actor_inbox'] ?? ($activity['inbox'] ?? '');
        $sharedInboxUrl = $activity['shared_inbox'] ?? null;

        if (!empty($inboxUrl)) {
            $this->followService->addFollower($actorUrl, (string) $inboxUrl, $sharedInboxUrl ? (string) $sharedInboxUrl : null);

            $fqdn = rtrim($this->site->metadata->fqdn ?? (string) Database::getSetting('fqdn'), '/');
            $handle = (string) (Database::getSetting('activitypub_handle') ?: 'author');
            $localActorUri = $fqdn . '/@' . $handle;

            $acceptId = $fqdn . '/activity/' . uniqid();
            $acceptActivity = ActivityBuilder::buildAcceptActivity($acceptId, $localActorUri, $activity);
            $this->outboxService->enqueueDelivery($acceptActivity, (string) $inboxUrl);
        }

        return true;
    }

    /**
     * Drains and processes queued items from inbox_queue.
     *
     * @param int $limit Maximum number of queued items to process.
     * @return int Number of processed items.
     */
    public function processPendingQueue(int $limit = 50): int
    {
        $stmt = $this->db->prepare('SELECT id, type, payload_json FROM inbox_queue ORDER BY id ASC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processed = 0;
        foreach ($items as $item) {
            $id = (int) $item['id'];
            $payload = (string) $item['payload_json'];
            $data = json_decode($payload, true);

            if (is_array($data)) {
                $this->handleActivity($data);
            }

            $del = $this->db->prepare('DELETE FROM inbox_queue WHERE id = ?');
            $del->execute([$id]);
            $processed++;
        }

        return $processed;
    }
}
