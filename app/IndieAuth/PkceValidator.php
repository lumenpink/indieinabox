<?php

declare(strict_types=1);

namespace Indieinabox\IndieAuth;

/**
 * Class PkceValidator
 *
 * Implements Proof Key for Code Exchange (PKCE) verification
 * supporting 'S256' and 'plain' code challenge methods (RFC 7636).
 */
class PkceValidator
{
    /**
     * Validates a code verifier against an expected code challenge.
     *
     * @param string $codeVerifier The plaintext verifier provided during token exchange.
     * @param string $codeChallenge The challenge originally provided during authorization.
     * @param string $method The challenge method ('S256' or 'plain').
     * @return bool True if the verifier matches the challenge, false otherwise.
     */
    public static function validate(string $codeVerifier, string $codeChallenge, string $method = 'plain'): bool
    {
        $calculated = self::calculateChallenge($codeVerifier, $method);
        return hash_equals($codeChallenge, $calculated);
    }

    /**
     * Calculates the expected code challenge from a code verifier.
     *
     * @param string $codeVerifier Plaintext code verifier.
     * @param string $method Challenge calculation method ('s256' or 'plain').
     * @return string Calculated challenge string.
     */
    public static function calculateChallenge(string $codeVerifier, string $method = 's256'): string
    {
        $normalizedMethod = strtolower($method);
        if ($normalizedMethod === 's256') {
            return self::base64UrlEncode(hash('sha256', $codeVerifier, true));
        }

        return $codeVerifier;
    }

    /**
     * Base64url-encodes a string (RFC 7636 Section 4.2).
     *
     * @param string $data Raw binary data.
     * @return string URL-safe base64 string without padding.
     */
    public static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
}
