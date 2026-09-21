<?php

declare(strict_types=1);

namespace Indieinabox\Services\Contracts;

/**
 * Interface ImportServiceInterface
 *
 * Defines contracts for inspecting and importing portable backup bundles.
 */
interface ImportServiceInterface
{
    /**
     * Inspects and validates an export bundle archive without importing or modifying site data.
     *
     * @param string $archivePath Path to .tar.gz, .tar, or .zip archive.
     * @return array{
     *     valid: bool,
     *     manifest: array<string, mixed>,
     *     errors: array<string>
     * }
     */
    public function inspect(string $archivePath): array;

    /**
     * Imports a portable export bundle with integrity validation and optional safety backup.
     *
     * @param string $archivePath Path to .tar.gz, .tar, or .zip archive.
     * @param array<string, mixed> $options [
     *     'verify_checksums' => bool,
     *     'safety_backup' => bool,
     *     'restore_database' => bool,
     *     'restore_content' => bool,
     *     'restore_media' => bool,
     *     'rebuild_site' => bool,
     * ]
     * @return array{
     *     success: bool,
     *     files_restored: int,
     *     safety_backup: ?string,
     *     manifest: array<string, mixed>
     * }
     */
    public function import(string $archivePath, array $options = []): array;
}
