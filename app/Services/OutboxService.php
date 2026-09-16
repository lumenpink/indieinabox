<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Core\Container;
use Indieinabox\Federation\Contracts\FederationAdapter;
use Indieinabox\Federation\FederationManager;
use Indieinabox\Repositories\Contracts\ActivityPubRepositoryInterface;
use Indieinabox\Repositories\SqliteActivityPubRepository;
use PDO;

/**
 * Service managing outgoing federation queues, fan-out broadcasting, and delivery execution.
 */
class OutboxService
{
    private FederationManager $federationManager;
    private FollowService $followService;
    private ActivityPubRepositoryInterface $repository;

    public function __construct(
        FederationManager|PDO|null $federationManager = null,
        ?FollowService $followService = null,
        ActivityPubRepositoryInterface|PDO|null $repository = null
    ) {
        $pdo = null;
        if ($federationManager instanceof PDO) {
            $pdo = $federationManager;
            $federationManager = null;
        } elseif ($repository instanceof PDO) {
            $pdo = $repository;
            $repository = null;
        }

        if ($repository instanceof ActivityPubRepositoryInterface) {
            $this->repository = $repository;
        } elseif ($pdo !== null) {
            $this->repository = new SqliteActivityPubRepository($pdo);
        } else {
            $container = class_exists(Container::class) ? Container::getInstance() : null;
            $this->repository = $container && $container->has(ActivityPubRepositoryInterface::class)
                ? $container->get(ActivityPubRepositoryInterface::class)
                : new SqliteActivityPubRepository();
        }

        $this->federationManager = $federationManager ?? new FederationManager();
        $this->followService = $followService ?? new FollowService($this->repository);
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

        return $this->repository->enqueueOutbox($payloadJson, $targetInbox, time());
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
        $messages = $this->repository->getPendingOutbox($limit);

        $successCount = 0;
        foreach ($messages as $msg) {
            $id = (int) $msg['id'];
            $payload = (string) $msg['payload_json'];
            $targetInbox = (string) $msg['target_inbox'];

            $delivered = $adapter->deliverActivity($payload, $targetInbox);
            $status = $delivered ? 'sent' : 'failed';

            $this->repository->updateOutboxStatus($id, $status);

            if ($delivered) {
                $successCount++;
            }
        }

        return $successCount;
    }
}
