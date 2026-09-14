<?php

declare(strict_types=1);

namespace Indieinabox;

/**
 * Class Version
 *
 * Provides application version information, git commit metadata, and build timestamps.
 */
class Version
{
    /**
     * Semantic version string of the current release.
     */
    public const VERSION = '0.2.0';

    /**
     * Gets the full version string including build metadata.
     *
     * @return string
     */
    public static function get(): string
    {
        if (defined('INDIEINABOX_COMPILED_VERSION')) {
            return (string) constant('INDIEINABOX_COMPILED_VERSION');
        }

        $baseVersion = self::VERSION;
        $gitCommit = self::getGitCommitHash();
        if ($gitCommit !== null) {
            return $baseVersion . '+git.' . $gitCommit;
        }

        return $baseVersion;
    }

    /**
     * Retrieves the short git commit hash if running within a git repository.
     *
     * @return string|null
     */
    public static function getGitCommitHash(): ?string
    {
        $gitDir = dirname(__DIR__) . '/.git';
        if (!is_dir($gitDir)) {
            return null;
        }

        $headFile = $gitDir . '/HEAD';
        if (!file_exists($headFile)) {
            return null;
        }

        $head = trim((string) @file_get_contents($headFile));
        if (strpos($head, 'ref: ') === 0) {
            $ref = substr($head, 5);
            $refPath = $gitDir . '/' . $ref;
            if (file_exists($refPath)) {
                $hash = trim((string) @file_get_contents($refPath));
                return substr($hash, 0, 7);
            }
            $packedRefsFile = $gitDir . '/packed-refs';
            if (file_exists($packedRefsFile)) {
                $lines = @file($packedRefsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        if (str_starts_with($line, '#') || str_starts_with($line, '^')) {
                            continue;
                        }
                        $parts = explode(' ', trim($line), 2);
                        if (count($parts) === 2 && $parts[1] === $ref && strlen($parts[0]) >= 7) {
                            return substr($parts[0], 0, 7);
                        }
                    }
                }
            }
        } elseif (strlen($head) >= 7) {
            return substr($head, 0, 7);
        }

        if (function_exists('exec')) {
            $output = [];
            $code = 0;
            @exec('git rev-parse --short HEAD 2>/dev/null', $output, $code);
            if ($code === 0 && !empty($output[0]) && preg_match('/^[a-f0-9]{7,40}$/i', trim($output[0]))) {
                return trim($output[0]);
            }
        }

        return null;
    }

    /**
     * Gets the base semantic version string.
     *
     * @return string
     */
    public static function getBaseVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Checks if the application is currently running as a single compiled executable.
     *
     * @return bool
     */
    public static function isCompiled(): bool
    {
        return defined('INDIEINABOX_COMPILED_VERSION');
    }

    /**
     * Gets the build timestamp if available.
     *
     * @return string|null
     */
    public static function getBuildDate(): ?string
    {
        if (defined('INDIEINABOX_BUILD_DATE')) {
            return (string) constant('INDIEINABOX_BUILD_DATE');
        }

        return null;
    }
}
