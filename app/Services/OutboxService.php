<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Database;
use Indieinabox\Federation\Contracts\FederationAdapter;
use Indieinabox\Federation\FederationManager;
use PDO;

/**
 * Service managing outgoing federation queues, fan-out broadcasting, and delivery execution.
 */
class OutboxService
{
    private FederationManager $federationManager;
    private FollowService $followService;
    private PDO $db;

    public function __construct(
        FederationManager|PDO|null $federationManager = null,
        ?FollowService $followService = null,
        ?PDO $db = null
    ) {
        if ($federationManager instanceof PDO) {
            $db = $federationManager;
            $federationManager = null;
        }

        $this->db = $db ?? Database::getDb();
        $this->federationManager = $federationManager ?? new FederationManager();
        $this->followService = $followService ?? new FollowService($this->db);
    }

    /**
     * Enqueues an activity delivery targeting a specific inbox endpoint.
     *
     * @param array<string, mixed>|string $payload
     * @param string $targetInbox
     * @return int Inserted message ID
     */
    public function enqueueDelivery(array|string $payload, string $targetInbox): int
    {
        $payloadJson = is_array($payload)
            ? (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : $payload;

        $stmt = $this->db->prepare(
            "INSERT INTO activitypub_outbox (payload_json, target_inbox, status, created_at) VALUES (?, ?, 'pending', ?)"
        );
        $stmt->execute([$payloadJson, $targetInbox, time()]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Broadcasts an activity to all distinct registered follower inboxes.
     *
     * @param array<string, mixed>|string $payload
     * @return int Number of inboxes queued
     */
    public function broadcastActivity(array|string $payload): int
    {
        $inboxes = $this->followService->getDistinctInboxes();
        $queued = 0;
        foreach ($inboxes as $inbox) {
            $this->enqueueDelivery($payload, $inbox);
            $queued++;
        }
        return $queued;
    }

    /**
     * Dispatches pending outbox queue items using the appropriate federation adapter.
     *
     * @param int $limit Maximum number of pending records to process.
     * @param string $protocol Protocol adapter to use for delivery.
     * @return int Number of successfully delivered messages.
     */
    public function dispatchPending(int $limit = 50, string $protocol = 'activitypub'): int
    {
        if (!$this->federationManager->has($protocol)) {
            return 0;
        }

        $adapter = $this->federationManager->get($protocol);

        $stmt = $this->db->prepare(
            "SELECT id, payload_json, target_inbox FROM activitypub_outbox WHERE status = 'pending' LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $successCount = 0;
        foreach ($messages as $msg) {
            $id = (int) $msg['id'];
            $payload = (string) $msg['payload_json'];
            $targetInbox = (string) $msg['target_inbox'];

            $delivered = $adapter->deliverActivity($payload, $targetInbox);
            $status = $delivered ? 'sent' : 'failed';

            $update = $this->db->prepare('UPDATE activitypub_outbox SET status = ? WHERE id = ?');
            $update->execute([$status, $id]);

            if ($delivered) {
                $successCount++;
            }
        }

        return $successCount;
    }
}
