<?php

declare(strict_types=1);

namespace Indieinabox\Services\Contracts;

/**
 * Interface contract for external media proxying, HMAC signature verification,
 * SSRF security validation, and local privacy caching.
 */
interface MediaProxyServiceInterface
{
    /**
     * Generates a signed proxy URL for an external media resource.
     * If the URL is already local, empty, or invalid, it is returned unmodified.
     *
     * @param string $url Target media URL.
     * @return string Signed proxy URL or original local URL.
     */
    public function generateProxiedUrl(string $url): string;

    /**
     * Verifies the cryptographic HMAC signature for a proxied media URL.
     *
     * @param string $url Original media URL.
     * @param string $signature Provided HMAC signature.
     * @return bool True if valid, false otherwise.
     */
    public function verifySignature(string $url, string $signature): bool;

    /**
     * Determines whether a given URL is safe to fetch (anti-SSRF validation).
     * Rejects non-HTTP(S) schemes, private/loopback/link-local/reserved IP ranges, and localhost.
     *
     * @param string $url Target URL to evaluate.
     * @return bool True if safe, false if potentially dangerous.
     */
    public function isSafeUrl(string $url): bool;

    /**
     * Returns the local cached filesystem path for a media URL if it exists, or null.
     *
     * @param string $url Target media URL.
     * @return string|null Filesystem path to cached file or null if not cached.
     */
    public function getCachedPath(string $url): ?string;

    /**
     * Fetches an external media resource securely (with SSRF protection, size limit,
     * and MIME validation), stores it in the local cache, and returns the file metadata.
     *
     * @param string $url External media URL.
     * @return array{path: string, mime: string, size: int}|null Metadata array or null on failure.
     */
    public function fetchAndCache(string $url): ?array;

    /**
     * Rewrites media elements (img, video, audio, source) in HTML content
     * to use signed proxied media URLs.
     *
     * @param string $html HTML content to transform.
     * @return string Transformed HTML content.
     */
    public function rewriteHtmlMedia(string $html): string;
}
