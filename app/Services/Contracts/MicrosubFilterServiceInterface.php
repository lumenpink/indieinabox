<?php

declare(strict_types=1);

namespace Indieinabox\Services\Contracts;

/**
 * Contract for Microsub feed filtering and moderation rules evaluation.
 */
interface MicrosubFilterServiceInterface
{
    /**
     * Evaluates a feed entry against active filtering rules for a given channel.
     * Returns the highest precedence action ('drop', 'mute', 'auto_archive') or null if no filter matches.
     *
     * @param array<string, mixed> $entry JF2 entry or parsed item structure.
     * @param string $channelUid Channel scope (defaults to '*').
     * @return string|null Matching action or null.
     */
    public function evaluate(array $entry, string $channelUid = '*'): ?string;

    /**
     * Checks if an entry should be muted (hidden from timeline).
     *
     * @param array<string, mixed> $entry
     * @param string $channelUid
     * @return bool
     */
    public function shouldMute(array $entry, string $channelUid = '*'): bool;

    /**
     * Checks if an entry should be automatically archived (marked read).
     *
     * @param array<string, mixed> $entry
     * @param string $channelUid
     * @return bool
     */
    public function shouldAutoArchive(array $entry, string $channelUid = '*'): bool;

    /**
     * Checks if an entry should be completely dropped/discarded during feed ingestion.
     *
     * @param array<string, mixed> $entry
     * @param string $channelUid
     * @return bool
     */
    public function shouldDrop(array $entry, string $channelUid = '*'): bool;

    /**
     * Retrieves all active filter rules, optionally filtered by channel scope.
     *
     * @param string|null $channelUid
     * @return array<int, array{id: int|string, filter_type: string, content: string, action: string, channel_uid: string, created_at: int|string}>
     */
    public function getFilters(?string $channelUid = null): array;

    /**
     * Creates a new filtering rule.
     *
     * @param string $filterType 'keyword', 'author', or 'domain'.
     * @param string $content Target keyword, author identifier, or domain name.
     * @param string $action 'mute', 'auto_archive', or 'drop'.
     * @param string $channelUid Channel UID or '*' for global.
     * @return int Created filter rule ID.
     */
    public function addFilter(string $filterType, string $content, string $action = 'mute', string $channelUid = '*'): int;

    /**
     * Deletes a filtering rule by ID.
     *
     * @param int $id
     * @return bool
     */
    public function deleteFilter(int $id): bool;
}
