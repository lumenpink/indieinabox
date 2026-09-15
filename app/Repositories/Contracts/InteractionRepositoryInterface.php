<?php

declare(strict_types=1);

namespace Indieinabox\Repositories\Contracts;

/**
 * Contract for storing, querying, and moderating incoming social interactions (likes, reposts, replies).
 */
interface InteractionRepositoryInterface
{
    /**
     * Retrieves approved incoming interactions for a specific page slug.
     *
     * @param string $slug Target page slug.
     * @param string|null $type Specific interaction type filter (e.g. 'like', 'reply', 'repost').
     * @return array<int, array<string, mixed>>
     */
    public function findByPageSlug(string $slug, ?string $type = null): array;

    /**
     * Lists interactions by status category ('pending', 'approved', 'spam').
     *
     * @return array<int, array<string, mixed>>
     */
    public function listByStatus(string $status = 'pending'): array;

    /**
     * Updates an interaction status ('approved', 'spam', 'pending').
     */
    public function updateStatus(string $id, string $status, string $type = 'pending'): bool;

    /**
     * Saves a new interaction record.
     *
     * @param string $id Unique interaction identifier.
     * @param array<string, mixed> $metadata Frontmatter metadata attributes.
     * @param string $content Formatted HTML or text content.
     * @param string $channel Target channel directory ('notifications', 'spam').
     */
    public function save(string $id, array $metadata, string $content = '', string $channel = 'notifications'): bool;

    /**
     * Deletes an interaction record.
     *
     * @param string $id
     * @param string $type Channel or status folder ('pending', 'spam', 'notifications').
     */
    public function delete(string $id, string $type = 'pending'): bool;
}
