<?php

declare(strict_types=1);

namespace Indieinabox\Versioning;

use Indieinabox\Page\Page;

/**
 * Class VersionManager
 *
 * Manages versioning lifecycle for individual posts via lightweight companion .history.json files.
 * Tracks content hashes, generates v{rev}.{YYYYMMDD}.{n} identifiers, and computes diffs
 * without modifying original markdown notes in Obsidian.
 */
class VersionManager
{
    /**
     * Determines if a Page is opted in for version tracking.
     *
     * @param Page $page
     * @return bool
     */
    public static function isVersioned(Page $page): bool
    {
        if (isset($page->metadata->versioned)) {
            $val = $page->metadata->versioned;
            if (is_bool($val)) {
                return $val;
            }
            if (is_string($val)) {
                $lower = strtolower(trim($val));
                return in_array($lower, ['true', 'yes', '1', 'on'], true);
            }
            if (is_numeric($val)) {
                return ((int) $val) > 0;
            }
        }

        return false;
    }

    /**
     * Resolves the companion .history.json path for a Page.
     *
     * @param Page $page
     * @return string|null
     */
    public static function getCompanionPath(Page $page): ?string
    {
        if (empty($page->filepath)) {
            return null;
        }

        $filepath = $page->filepath;
        if (str_ends_with(strtolower($filepath), '.md')) {
            return substr($filepath, 0, -3) . '.history.json';
        }

        return $filepath . '.history.json';
    }

    /**
     * Processes a Page during build:
     * - Loads or initializes its companion history.
     * - Checks if body content has changed via SHA-256.
     * - If changed, records a new version and computes diff.
     * - Decorates $page->metadata with version information.
     *
     * @param Page $page
     * @param int|null $customTimestamp Optional timestamp for test determinism.
     * @return array<string, mixed>|null The loaded or updated history structure.
     */
    public static function processPage(Page $page, ?int $customTimestamp = null): ?array
    {
        if (!self::isVersioned($page)) {
            return null;
        }

        $companionPath = self::getCompanionPath($page);
        if ($companionPath === null) {
            return null;
        }

        $now = $customTimestamp ?? time();
        $dateStr = gmdate('Y-m-d\TH:i:s\Z', $now);
        $ymd = gmdate('Ymd', $now);

        $rawBody = self::extractNormalizedBody($page);
        $contentHash = hash('sha256', $rawBody);
        $versionNote = $page->metadata->version_note
            ?? $page->metadata->versionNote
            ?? $page->metadata->summary
            ?? null;

        $history = [
            'slug' => $page->slug,
            'current_version' => '',
            'current_hash' => '',
            'versions' => [],
        ];

        if (file_exists($companionPath)) {
            $json = @file_get_contents($companionPath);
            if ($json !== false) {
                $decoded = json_decode($json, true);
                if (is_array($decoded) && isset($decoded['versions']) && is_array($decoded['versions'])) {
                    $history = $decoded;
                }
            }
        }

        $versions = $history['versions'] ?? [];
        $lastVersion = !empty($versions) ? end($versions) : null;
        $needsNewVersion = false;

        if (empty($versions)) {
            // Initial version
            $rev = 1;
            $subrev = 1;
            $versionTag = "v{$rev}.{$ymd}.{$subrev}";

            $note = $versionNote ?: 'Versão inicial';
            $bytes = strlen($rawBody);

            $versionRecord = [
                'version' => $versionTag,
                'rev' => $rev,
                'date' => $dateStr,
                'timestamp' => $now,
                'hash' => $contentHash,
                'note' => $note,
                'bytes' => $bytes,
                'bytes_diff' => $bytes,
                'diff' => null,
                'content' => $rawBody,
            ];

            $history['slug'] = $page->slug;
            $history['current_version'] = $versionTag;
            $history['current_hash'] = $contentHash;
            $history['versions'] = [$versionRecord];
            $needsNewVersion = true;
        } elseif ($history['current_hash'] !== $contentHash) {
            // Content has changed!
            $rev = count($versions) + 1;

            // Count existing versions on the same day to compute subrev
            $todayCount = 0;
            foreach ($versions as $v) {
                $vDate = $v['date'] ?? '';
                if (str_starts_with($vDate, gmdate('Y-m-d', $now))) {
                    $todayCount++;
                }
            }
            $subrev = $todayCount + 1;
            $versionTag = "v{$rev}.{$ymd}.{$subrev}";

            $prevContent = (string) ($lastVersion['content'] ?? '');
            $diff = Differ::unifiedDiff($prevContent, $rawBody, (string) ($lastVersion['version'] ?? 'old'), $versionTag);
            $bytes = strlen($rawBody);
            $prevBytes = strlen($prevContent);
            $bytesDiff = $bytes - $prevBytes;

            $note = $versionNote ?: "Edição {$versionTag}";

            $versionRecord = [
                'version' => $versionTag,
                'rev' => $rev,
                'date' => $dateStr,
                'timestamp' => $now,
                'hash' => $contentHash,
                'note' => $note,
                'bytes' => $bytes,
                'bytes_diff' => $bytesDiff,
                'diff' => $diff,
                'content' => $rawBody,
            ];

            $history['current_version'] = $versionTag;
            $history['current_hash'] = $contentHash;
            $history['versions'][] = $versionRecord;
            $needsNewVersion = true;
        }

        if ($needsNewVersion) {
            $dir = dirname($companionPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            file_put_contents(
                $companionPath,
                json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
        }

        // Decorate Page metadata
        $page->metadata->version = $history['current_version'];
        $page->metadata->version_count = count($history['versions']);
        $page->metadata->version_history = $history;

        return $history;
    }

    /**
     * Extracts normalized markdown body from page.
     *
     * @param Page $page
     * @return string
     */
    public static function extractNormalizedBody(Page $page): string
    {
        if (isset($page->rawBody) && is_string($page->rawBody) && $page->rawBody !== '') {
            return str_replace(["\r\n", "\r"], "\n", trim($page->rawBody));
        }

        if (!empty($page->filepath) && file_exists($page->filepath)) {
            $fullContent = (string) @file_get_contents($page->filepath);
            // Strip frontmatter if present
            if (preg_match('/^---[\r\n]+(.*?[\r\n]+)---[\r\n]*(.*)$/s', $fullContent, $matches)) {
                return str_replace(["\r\n", "\r"], "\n", trim($matches[2]));
            }
            return str_replace(["\r\n", "\r"], "\n", trim($fullContent));
        }

        if (isset($page->content) && isset($page->content->raw)) {
            return str_replace(["\r\n", "\r"], "\n", trim((string) $page->content->raw));
        }

        return '';
    }
}
