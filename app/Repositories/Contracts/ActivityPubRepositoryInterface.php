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

    /**
     * Records an activity created by the local actor or C2S client.
     */
    public function recordActivity(string $activityId, string $type, string $actor, string $payloadJson, ?int $createdAt = null): bool;

    /**
     * Retrieves recorded outbox activities.
     *
     * @return array<int, array{id: int, activity_id: string, type: string, actor: string, payload_json: string, created_at: int}>
     */
    public function getActivities(int $limit = 50, int $offset = 0): array;

    /**
     * Gets the total count of recorded outbox activities.
     */
    public function getActivityCount(): int;

    /**
     * Adds a follower to a specific flowerbed (Group Actor).
     */
    public function addFlowerbedFollower(string $flowerbedSlug, string $actorUrl, string $inboxUrl, ?string $sharedInboxUrl = null): bool;

    /**
     * Removes a follower from a specific flowerbed.
     */
    public function removeFlowerbedFollower(string $flowerbedSlug, string $actorUrl): bool;

    /**
     * Retrieves all follower inboxes for a specific flowerbed.
     *
     * @return array<int, string>
     */
    public function getFlowerbedFollowerInboxes(string $flowerbedSlug): array;
}
