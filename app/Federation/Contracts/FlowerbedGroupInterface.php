<?php

declare(strict_types=1);

namespace Indieinabox\Federation\Contracts;

/**
 * Interface FlowerbedGroupInterface
 *
 * Defines the contract for Group Actor operations on flowerbeds (canteiros)
 * conforming to FEP-1b12 / Lemmy / Kbin community federation.
 */
interface FlowerbedGroupInterface
{
    /**
     * Generates the ActivityStreams Group Actor definition for a flowerbed.
     *
     * @param string $slug Flowerbed identifier slug.
     * @return array<string, mixed>
     */
    public function getActor(string $slug): array;

    /**
     * Handles an incoming activity directed to the flowerbed's inbox (Follow, Undo, etc.).
     *
     * @param string $slug Flowerbed identifier slug.
     * @param array<string, mixed> $activity Received ActivityStreams payload.
     * @return array{status: int, body: array<string, mixed>}
     */
    public function handleInbox(string $slug, array $activity): array;

    /**
     * Retrieves the flowerbed's outbox OrderedCollection of announces.
     *
     * @param string $slug Flowerbed identifier slug.
     * @return array<string, mixed>
     */
    public function getOutbox(string $slug): array;

    /**
     * Retrieves the flowerbed's followers OrderedCollection.
     *
     * @param string $slug Flowerbed identifier slug.
     * @return array<string, mixed>
     */
    public function getFollowers(string $slug): array;

    /**
     * Distributes an announce of a post to all group followers (Fan-out).
     *
     * @param string $slug Flowerbed identifier slug.
     * @param string $postUrl Canonical URL of the post.
     * @param string $title Title or summary of the post.
     * @return int Number of inboxes enqueued.
     */
    public function fanOutPost(string $slug, string $postUrl, string $title = ''): int;
}
