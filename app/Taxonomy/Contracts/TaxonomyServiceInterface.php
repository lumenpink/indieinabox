<?php

declare(strict_types=1);

namespace Indieinabox\Taxonomy\Contracts;

use Indieinabox\Page\Page;

/**
 * Interface TaxonomyServiceInterface
 *
 * Defines contract for managing post kind taxonomy, kind configuration,
 * URL mapping, and slug translations.
 */
interface TaxonomyServiceInterface
{
    /**
     * Retrieves kind configuration with sensible defaults.
     *
     * @param string $kind
     * @return array<string, mixed>
     */
    public function getKindConfig(string $kind): array;

    /**
     * Determines the kind and localized folder for a page.
     *
     * @param Page|array<string, mixed> $page
     * @return array{localized: string, kind: string}
     */
    public function resolveKind(mixed $page): array;

    /**
     * Get the localized folder name for a specific kind and language.
     *
     * @param string $kind
     * @param string $lang
     * @return string
     */
    public function getKindFolder(string $kind, string $lang): string;

    /**
     * Return a human-readable, localized display label for a post kind.
     *
     * @param string $kind
     * @param string|null $lang
     * @return string
     */
    public function getKindLabel(string $kind, ?string $lang = null): string;

    /**
     * Return a hyperlinked, human-readable display label for a post kind.
     *
     * @param Page $page
     * @param string $kind
     * @return string
     */
    public function getKindLink(Page $page, string $kind): string;

    /**
     * Get original content slug translation.
     *
     * @param string $slug
     * @param string $lang
     * @return string
     */
    public function getOriginalContent(string $slug, string $lang): string;

    /**
     * Check if a page or kind is eligible to be shown on home/recent listings.
     *
     * @param mixed $var
     * @return bool
     */
    public function isListingEligible(mixed $var): bool;
}
