<?php

declare(strict_types=1);

namespace Indieinabox\Federation\Contracts;

/**
 * Interface defining protocol adapters for federated networks.
 */
interface FederationAdapter
{
    /**
     * Unique protocol identifier (e.g. 'activitypub', 'twtxt', 'lemmy').
     */
    public function getProtocol(): string;

    /**
     * Checks if this adapter handles the specified network or protocol.
     */
    public function supports(string $protocol): bool;

    /**
     * Builds a like/favorite activity payload for the target object.
     *
     * @param string $targetUrl
     * @return array<string, mixed>
     */
    public function buildLikeActivity(string $targetUrl): array;

    /**
     * Builds a reply/comment activity payload.
     *
     * @param string $targetUrl
     * @param string $content
     * @param string|null $inReplyTo
     * @return array<string, mixed>
     */
    public function buildReplyActivity(string $targetUrl, string $content, ?string $inReplyTo = null): array;

    /**
     * Builds a follow request activity payload for a target actor.
     *
     * @param string $targetActorUri
     * @return array<string, mixed>
     */
    public function buildFollowActivity(string $targetActorUri): array;

    /**
     * Delivers an activity payload to a remote recipient endpoint.
     *
     * @param array<string, mixed>|string $activity
     * @param string $destinationUrl
     * @return bool
     */
    public function deliverActivity(array|string $activity, string $destinationUrl): bool;

    /**
     * Parses an incoming raw activity payload into a normalized array.
     *
     * @param string $payload
     * @return array<string, mixed>|null
     */
    public function parseActivity(string $payload): ?array;
}
