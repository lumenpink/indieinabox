<?php

declare(strict_types=1);

namespace Indieinabox\Theme\Contracts;

use Indieinabox\Site\Site;

/**
 * Interface ThemeEcosystemInterface
 *
 * Defines methods for theme discovery, manifest validation, zip/git installation,
 * and activation in the Theme Ecosystem.
 */
interface ThemeEcosystemInterface
{
    /**
     * Lists all installed and available themes.
     *
     * @param ?string $baseThemesDir Optional path to the themes directory.
     * @return array<int, array<string, mixed>>
     */
    public function listThemes(?string $baseThemesDir = null): array;

    /**
     * Validates a theme.json manifest structure.
     *
     * @param array<string, mixed> $manifest
     * @return array{valid: bool, errors: list<string>}
     */
    public function validateManifest(array $manifest): array;

    /**
     * Installs a theme from a ZIP package file.
     *
     * @param string $zipPath Path to the uploaded/downloaded zip archive.
     * @param ?string $baseThemesDir Optional path to the themes directory.
     * @return array{success: bool, slug?: string, manifest?: array<string, mixed>, error?: string}
     */
    public function installFromZip(string $zipPath, ?string $baseThemesDir = null): array;

    /**
     * Installs a theme by cloning a Git repository.
     *
     * @param string $gitUrl Git clone repository URL.
     * @param ?string $targetSlug Optional explicit theme slug folder.
     * @param ?string $baseThemesDir Optional path to the themes directory.
     * @return array{success: bool, slug?: string, manifest?: array<string, mixed>, error?: string}
     */
    public function installFromGit(string $gitUrl, ?string $targetSlug = null, ?string $baseThemesDir = null): array;

    /**
     * Activates a theme by setting the site's theme directory and persistent setting.
     *
     * @param string $slug Theme folder slug.
     * @param ?Site $site Optional Site instance.
     * @param ?string $baseThemesDir Optional path to the themes directory.
     * @return bool
     */
    public function activateTheme(string $slug, ?Site $site = null, ?string $baseThemesDir = null): bool;
}
