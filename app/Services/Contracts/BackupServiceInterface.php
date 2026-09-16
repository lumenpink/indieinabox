<?php

declare(strict_types=1);

namespace Indieinabox\Services\Contracts;

/**
 * Interface BackupServiceInterface
 *
 * Defines automated archive creation and rotation for site data, content, and configuration.
 */
interface BackupServiceInterface
{
    /**
     * Executes backup archive generation.
     */
    public function run(bool $skipContent = false, bool $skipMedia = false): void;

    /**
     * Rotates existing backups keeping only the most recent $limit files.
     */
    public function rotateBackups(string $destDir, int $limit = 5): void;
}
