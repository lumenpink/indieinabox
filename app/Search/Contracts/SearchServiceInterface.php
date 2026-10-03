<?php

declare(strict_types=1);

namespace Indieinabox\Search\Contracts;

use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;

/**
 * Interface SearchServiceInterface
 *
 * Defines contract for full-text search indexing and querying using SQLite FTS5.
 */
interface SearchServiceInterface
{
    /**
     * Ensures the FTS5 virtual table exists in the database.
     */
    public function ensureTable(): void;

    /**
     * Indexes a single generic document into FTS5.
     */
    public function indexDocument(
        string $docId,
        string $docType,
        string $title,
        string $content,
        string $tags = '',
        string $url = '',
        string $publishedAt = '',
        string $author = '',
        string $lang = 'en'
    ): bool;

    /**
     * Removes a document from the FTS5 index.
     */
    public function deleteDocument(string $docId): bool;

    /**
     * Indexes a domain Page (article, note, garden note, photo, etc.).
     */
    public function indexPage(Page $page): bool;

    /**
     * Bulk indexes all pages in the Pages collection.
     */
    public function indexAllPages(Pages $pages): int;

    /**
     * Indexes an ingested Microsub / Twtxt feed item.
     *
     * @param array<string, mixed> $entry
     */
    public function indexFeedEntry(array $entry, string $channel = 'inbox'): bool;

    /**
     * Scans and indexes all feed items in data/microsub/inbox/.
     */
    public function indexAllFeeds(): int;

    /**
     * Rebuilds the search index from scratch for both posts and feeds.
     *
     * @return array{posts: int, feeds: int}
     */
    public function rebuildIndex(Pages $pages): array;

    /**
     * Performs a full-text search query using SQLite FTS5 ranking and snippet extraction.
     *
     * @param array<string, mixed> $options Filter options: 'type', 'lang', 'limit', 'offset'.
     * @return array{query: string, total: int, limit: int, offset: int, results: array<int, array<string, mixed>>}
     */
    public function search(string $query, array $options = []): array;
}
