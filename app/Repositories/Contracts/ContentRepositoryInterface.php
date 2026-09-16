<?php

declare(strict_types=1);

namespace Indieinabox\Repositories\Contracts;

/**
 * Interface ContentRepositoryInterface
 *
 * Defines persistence and retrieval operations for filesystem-based Markdown posts and pages.
 */
interface ContentRepositoryInterface
{
    /**
     * Persists a markdown post with frontmatter to the appropriate content directory hierarchy.
     *
     * @param string $kind Post kind (e.g. 'article', 'note', 'bookmark')
     * @param string $slug Unique slug identifier
     * @param string $content Markdown content body
     * @param array<string, mixed> $frontmatter Metadata key-value pairs
     * @param string|null $lang Language subfolder (optional)
     * @param string|null $year Optional year directory override (defaults to current year)
     * @param string|null $month Optional month directory override (defaults to current month)
     * @return string Absolute file path of the saved post
     */
    public function save(
        string $kind,
        string $slug,
        string $content,
        array $frontmatter = [],
        ?string $lang = null,
        ?string $year = null,
        ?string $month = null
    ): string;

    /**
     * Retrieves the raw content of a post file by its absolute path.
     */
    public function findByPath(string $filepath): ?string;

    /**
     * Deletes a post file by its absolute path.
     */
    public function delete(string $filepath): bool;

    /**
     * Checks if a post file exists at the given path.
     */
    public function exists(string $filepath): bool;

    /**
     * Generates a collision-free slug by appending incremental numeric suffixes if necessary.
     */
    public function generateUniqueSlug(
        string $kind,
        string $baseSlug,
        ?string $lang = null,
        ?string $year = null,
        ?string $month = null
    ): string;

    /**
     * Formats metadata and body into a YAML-frontmatter Markdown string.
     *
     * @param array<string, mixed> $frontmatter
     * @param string $body
     * @return string
     */
    public function buildFrontmatterMarkdown(array $frontmatter, string $body): string;

    /**
     * Parses a raw Markdown string with frontmatter into an associative array.
     *
     * @param string $rawContent
     * @return array{frontmatter: array<string, mixed>, body: string}
     */
    public function parseFrontmatterMarkdown(string $rawContent): array;

    /**
     * Recursively scans a content directory for Markdown (.md) files.
     *
     * @param string $dir
     * @return array<int, string> List of absolute file paths
     */
    public function scan(string $dir): array;
}
