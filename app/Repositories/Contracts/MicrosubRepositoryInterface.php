<?php

declare(strict_types=1);

namespace Indieinabox\Repositories\Contracts;

/**
 * Interface MicrosubRepositoryInterface
 *
 * Defines persistence operations for Microsub channels and subscriptions.
 */
interface MicrosubRepositoryInterface
{
    /**
     * Retrieves all channels.
     *
     * @return array<int, array{uid: string, name: string}>
     */
    public function getChannels(): array;

    /**
     * Creates a new channel.
     */
    public function createChannel(string $uid, string $name): bool;

    /**
     * Deletes a channel and all associated subscriptions.
     */
    public function deleteChannel(string $uid): bool;

    /**
     * Retrieves all subscriptions for a channel.
     *
     * @return array<int, array{url: string, type: string, name: string, photo: string}>
     */
    public function getSubscriptions(string $channelUid): array;

    /**
     * Adds a subscription to a channel.
     */
    public function addSubscription(
        string $channelUid,
        string $url,
        string $type = 'feed',
        string $name = '',
        string $photo = ''
    ): bool;

    /**
     * Gets the subscription type (e.g. 'feed' or 'actor') for a channel and url.
     */
    public function getSubscriptionType(string $channelUid, string $url): ?string;

    /**
     * Removes a subscription from a channel.
     */
    public function removeSubscription(string $channelUid, string $url): bool;

    /**
     * Counts how many channels are subscribed to a given URL.
     */
    public function countSubscriptionsByUrl(string $url): int;
}
