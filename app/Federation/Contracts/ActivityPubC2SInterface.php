<?php

declare(strict_types=1);

namespace Indieinabox\Federation\Contracts;

/**
 * Interface ActivityPubC2SInterface
 *
 * Defines the contract for processing W3C ActivityPub Client-to-Server (Section 6)
 * submissions to the actor's outbox and querying the outbox collection.
 */
interface ActivityPubC2SInterface
{
    /**
     * Handles an authenticated client submission to the outbox.
     *
     * @param array<string, mixed> $payload The submitted activity or object.
     * @param array{me: string, client_id: string, scope: string} $auth Authenticated client context.
     * @return array{status: int, headers: array<string, string>, body: array<string, mixed>}
     */
    public function handleSubmission(array $payload, array $auth): array;

    /**
     * Retrieves the outbox as an ActivityStreams OrderedCollection.
     *
     * @param int $limit
     * @param int $offset
     * @return array<string, mixed>
     */
    public function getOutboxCollection(int $limit = 50, int $offset = 0): array;
}
