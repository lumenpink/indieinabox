<?php

declare(strict_types=1);

namespace Indieinabox\Services\Contracts;

/**
 * Interface ExportServiceInterface
 *
 * Contract for creating portable export bundles containing database, content, media, and manifest.
 */
interface ExportServiceInterface
{
    /**
     * Creates an export bundle containing database, content, media, and manifest.
     *
     * @param string|null $outputPath Optional destination file path (.tar.gz or .zip).
     * @param array<string, mixed> $options Export options (e.g. skip_media, skip_content).
     * @return array{success: bool, path: string, size_bytes: int, manifest: array<string, mixed>, error?: string}
     */
    public function export(?string $outputPath = null, array $options = []): array;
}
