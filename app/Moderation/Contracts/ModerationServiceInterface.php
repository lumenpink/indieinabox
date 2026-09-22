<?php

declare(strict_types=1);

namespace Indieinabox\Moderation\Contracts;

/**
 * Interface ModerationServiceInterface
 *
 * Defines contracts for defederation, domain/actor blocking, silencing, and moderation audit logging.
 */
interface ModerationServiceInterface
{
    /**
     * Adds a defederation or moderation rule.
     *
     * @param ?string $domain Fully qualified domain name (e.g. 'bad-instance.social').
     * @param ?string $actorUrl Specific actor or author URL.
     * @param string $action 'block', 'silence', or 'reject_media'.
     * @param string $reason Audit log reason for moderation action.
     * @return int Inserted rule ID.
     */
    public function addBlock(?string $domain, ?string $actorUrl = null, string $action = 'block', string $reason = ''): int;

    /**
     * Removes an existing block rule by ID.
     *
     * @param int $id Rule identifier.
     * @return bool
     */
    public function removeBlock(int $id): bool;

    /**
     * Retrieves all active moderation blocks.
     *
     * @return array<int, array{id: int, domain: ?string, actor_url: ?string, action: string, reason: ?string, created_at: int}>
     */
    public function getBlocks(): array;

    /**
     * Checks if a domain or actor is blocked from interacting with the instance.
     *
     * @param ?string $domain
     * @param ?string $actorUrl
     * @return bool
     */
    public function isBlocked(?string $domain, ?string $actorUrl = null): bool;

    /**
     * Checks if a domain or actor is silenced.
     *
     * @param ?string $domain
     * @param ?string $actorUrl
     * @return bool
     */
    public function isSilenced(?string $domain, ?string $actorUrl = null): bool;

    /**
     * Inspects an actor and/or domain returning the matching moderation action if any.
     *
     * @param ?string $actorUrl
     * @param ?string $domain
     * @return ?string Action ('block', 'silence', 'reject_media') or null if clean.
     */
    public function checkAction(?string $actorUrl, ?string $domain = null): ?string;
}
