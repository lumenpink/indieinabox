<?php

declare(strict_types=1);

namespace Indieinabox\Repositories\Contracts;

/**
 * Contract for application settings, kind definitions, and translations storage.
 */
interface SettingsRepositoryInterface
{
    /**
     * Retrieves a single setting by key, with optional default fallback.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Persists or updates a single setting.
     */
    public function set(string $key, mixed $value): bool;

    /**
     * Retrieves all settings as an associative key-value map.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Retrieves interface translations grouped by [phrase_key][lang] = phrase_value.
     *
     * @return array<string, array<string, string>>
     */
    public function getTranslations(): array;

    /**
     * Retrieves localized slug translations grouped by [slug_key][lang] = slug_value.
     *
     * @return array<string, array<string, string>>
     */
    public function getUrlTranslations(): array;

    /**
     * Retrieves content kinds definitions keyed by kind_key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getKinds(): array;

    /**
     * Saves or replaces kind configurations.
     *
     * @param array<string, array<string, mixed>> $kinds
     */
    public function saveKinds(array $kinds): bool;

    /**
     * Saves or updates phrase translations.
     *
     * @param array<string, array<string, string>> $translations
     */
    public function saveTranslations(array $translations): bool;

    /**
     * Saves or updates localized slug translations.
     *
     * @param array<string, array<string, string>> $urlTranslations
     */
    public function saveUrlTranslations(array $urlTranslations): bool;
}
