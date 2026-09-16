<?php

declare(strict_types=1);

namespace Indieinabox\Repositories\Contracts;

/**
 * Interface ActivityPubRepositoryInterface
 *
 * Defines persistence operations for ActivityPub followers and outgoing delivery queues.
 */
interface ActivityPubRepositoryInterface
{
    /**
     * Records or updates a remote actor follower.
     */
    public function addFollower(string $actorUrl, string $inboxUrl, ?string $sharedInboxUrl = null): bool;

    /**
     * Removes an active follower record.
     */
    public function removeFollower(string $actorUrl): bool;

    /**
     * Checks if a remote actor is a registered follower.
     */
    public function isFollower(string $actorUrl): bool;

    /**
     * Retrieves all follower records.
     *
     * @return array<int, array{actor_url: string, inbox_url: string, shared_inbox_url: ?string}>
     */
    public function getFollowers(): array;

    /**
     * Returns deduplicated target inbox endpoints (prioritizing shared inboxes).
     *
     * @return array<int, string>
     */
    public function getDistinctInboxes(): array;

    /**
     * Enqueues a payload for delivery to a target inbox.
     *
     * @return int Inserted record ID
     */
    public function enqueueOutbox(string $payloadJson, string $targetInbox, ?int $createdAt = null): int;

    /**
     * Retrieves pending outbox items up to the specified limit.
     *
     * @return array<int, array{id: int, payload_json: string, target_inbox: string}>
     */
    public function getPendingOutbox(int $limit = 50): array;

    /**
     * Updates delivery status for an outbox record ('sent', 'failed', etc.).
     */
    public function updateOutboxStatus(int $id, string $status): bool;

    /**
     * Prunes processed outbox records older than a given timestamp.
     *
     * @return int Number of rows pruned
     */
    public function pruneOutbox(int $olderThanTimestamp): int;
}
