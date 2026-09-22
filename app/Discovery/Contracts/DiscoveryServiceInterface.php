<?php

declare(strict_types=1);

namespace Indieinabox\Discovery\Contracts;

/**
 * Interface DiscoveryServiceInterface
 *
 * Defines the contract for discovering and aggregating public timeline entries
 * across local content and federated networks (ActivityPub, Twtxt, RSS/Atom).
 */
interface DiscoveryServiceInterface
{
    /**
     * Retrieves aggregated public timeline entries matching the given criteria.
     *
     * @param array{
     *     network?: string|null,
     *     tag?: string|null,
     *     search?: string|null,
     * } $filters
     * @param int $limit Maximum number of entries to return.
     * @param int $offset Offset for pagination.
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     total: int,
     *     networks: array<string, int>,
     *     tags: array<string, int>,
     * }
     */
    public function getTimeline(array $filters = [], int $limit = 20, int $offset = 0): array;

    /**
     * Clears any cached discovery results.
     */
    public function clearCache(): void;
}
