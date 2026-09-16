<?php

declare(strict_types=1);

namespace Indieinabox\Services\Contracts;

/**
 * Interface UpdateServiceInterface
 *
 * Defines version checking, binary download, backup, and rollback capabilities.
 */
interface UpdateServiceInterface
{
    public const API_URL = 'https://codeberg.org/api/v1/repos/indieinabox/indieinabox/releases';
    public const MAX_BACKUPS = 2;

    /**
     * Returns the directory where version backups are stored.
     */
    public function getVersionsDir(): string;

    /**
     * Checks remote repository for available releases.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checkAvailableVersions(): array;

    /**
     * Retrieves currently cached available updates.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableUpdates(): array;

    /**
     * Finds latest release matching stability preference.
     *
     * @return array<string, mixed>|null
     */
    public function getLatestRelease(bool $includePrerelease = false): ?array;

    /**
     * Downloads and installs update from the specified URL.
     */
    public function downloadAndInstall(string $downloadUrl): bool;

    /**
     * Backs up current executable, enforcing MAX_BACKUPS retention.
     *
     * @return string|false
     */
    public function backupCurrentVersion(): string|false;

    /**
     * Returns list of local executable backup files.
     *
     * @return array<int, array{filename: string, path: string, version: ?string, date: int, formatted_date: string, size: int}>
     */
    public function getLocalBackups(): array;

    /**
     * Rolls back executable to a previous version from backup.
     */
    public function rollback(?string $backupFilename = null): bool;

    /**
     * Checks and applies updates based on scheduled interval and auto-upgrade settings.
     *
     * @return array{checked: bool, upgraded: bool, message: string}
     */
    public function processScheduledUpdate(int $intervalSeconds = 21600): array;

    /**
     * Enforces MAX_BACKUPS retention limit.
     */
    public function cleanupOldBackups(): int;

    /**
     * Determines path to current main executable.
     */
    public function getCurrentExecutablePath(): string;
}
