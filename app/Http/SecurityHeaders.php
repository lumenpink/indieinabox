<?php

declare(strict_types=1);

namespace Indieinabox\Http;

/**
 * Class SecurityHeaders
 *
 * Emits defensive HTTP headers for Content Security Policy, frame options,
 * sniffing prevention, referrer policy, and HTTPS strict transport security.
 */
class SecurityHeaders
{
    /**
     * Applies standard hardened HTTP security headers.
     *
     * @param bool $isHttps
     */
    public static function apply(bool $isHttps = false): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https: blob:; media-src 'self' https: blob:; font-src 'self' data: https:; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self' https:; frame-ancestors 'self';");

        $isSsl = $isHttps
            || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        if ($isSsl) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
