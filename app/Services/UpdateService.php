<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Core\Container;
use Indieinabox\Services\Contracts\UpdateServiceInterface;

/**
 * Static facade for UpdateServiceInterface operations.
 */
class UpdateService
{
    public const API_URL = UpdateServiceInterface::API_URL;
    public const MAX_BACKUPS = UpdateServiceInterface::MAX_BACKUPS;

    /**
     * Optional override for the executable path (used for testing and custom runners).
     */
    public static ?string $customExecutablePath = null;

    /**
     * Optional override for versions directory (used for testing).
     */
    public static ?string $customVersionsDir = null;

    private static function getManager(): UpdateServiceInterface
    {
        if (class_exists(Container::class)) {
            $container = Container::getInstance();
            if ($container->has(UpdateServiceInterface::class)) {
                return $container->get(UpdateServiceInterface::class);
            }
        }
        return new UpdateManager();
    }

    public static function getVersionsDir(): string
    {
        return self::getManager()->getVersionsDir();
    }

    public static function checkAvailableVersions(): array
    {
        return self::getManager()->checkAvailableVersions();
    }

    public static function getAvailableUpdates(): array
    {
        return self::getManager()->getAvailableUpdates();
    }

    public static function getLatestRelease(bool $includePrerelease = false): ?array
    {
        return self::getManager()->getLatestRelease($includePrerelease);
    }

    public static function downloadAndInstall(string $downloadUrl): bool
    {
        return self::getManager()->downloadAndInstall($downloadUrl);
    }

    public static function backupCurrentVersion(): string|false
    {
        return self::getManager()->backupCurrentVersion();
    }

    public static function rollback(?string $backupFilename = null): bool
    {
        return self::getManager()->rollback($backupFilename);
    }

    public static function getLocalBackups(): array
    {
        return self::getManager()->getLocalBackups();
    }

    public static function cleanupOldBackups(): int
    {
        return self::getManager()->cleanupOldBackups();
    }

    public static function processScheduledUpdate(int $intervalSeconds = 21600): array
    {
        return self::getManager()->processScheduledUpdate($intervalSeconds);
    }

    public static function getCurrentExecutablePath(): string
    {
        return self::getManager()->getCurrentExecutablePath();
    }
}
