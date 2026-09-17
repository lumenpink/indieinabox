<?php

declare(strict_types=1);

namespace Indieinabox\Services\Contracts;

use Indieinabox\DTO\InteractionDto;

/**
 * Interface IngestInteractionServiceInterface
 *
 * Defines operations for ingesting, validating, and persisting incoming social interactions.
 */
interface IngestInteractionServiceInterface
{
    /**
     * Ingests a normalized interaction into the repository and dispatches associated domain events.
     *
     * @param InteractionDto $interaction
     * @return bool
     */
    public function ingest(InteractionDto $interaction): bool;

    /**
     * Ingests a verified Webmention payload.
     *
     * @param string $source
     * @param string $target
     * @param array<string, mixed> $verifiedContent
     * @param string $status
     * @return InteractionDto
     */
    public function ingestWebmention(
        string $source,
        string $target,
        array $verifiedContent,
        string $status = "pending"
    ): InteractionDto;

    /**
     * Ingests a federated ActivityPub activity.
     *
     * @param array<string, mixed> $activity
     * @param array<string, mixed>|null $actorData
     * @param string $status
     * @return InteractionDto|null
     */
    public function ingestActivity(
        array $activity,
        ?array $actorData = null,
        string $status = "pending"
    ): ?InteractionDto;
}
