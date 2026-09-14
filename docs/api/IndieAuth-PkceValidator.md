# PkceValidator
**Namespace:** `Indieinabox\IndieAuth`

Class PkceValidator

Implements Proof Key for Code Exchange (PKCE) verification
supporting 'S256' and 'plain' code challenge methods (RFC 7636).

## Methods

### validate()
`public static function validate(string $codeVerifier, string $codeChallenge, string $method = 'plain'): bool`

Validates a code verifier against an expected code challenge.

@param string $codeVerifier The plaintext verifier provided during token exchange.
@param string $codeChallenge The challenge originally provided during authorization.
@param string $method The challenge method ('S256' or 'plain').
@return bool True if the verifier matches the challenge, false otherwise.

### calculateChallenge()
`public static function calculateChallenge(string $codeVerifier, string $method = 's256'): string`

Calculates the expected code challenge from a code verifier.

@param string $codeVerifier Plaintext code verifier.
@param string $method Challenge calculation method ('s256' or 'plain').
@return string Calculated challenge string.

### base64UrlEncode()
`public static function base64UrlEncode(string $data): string`

Base64url-encodes a string (RFC 7636 Section 4.2).

@param string $data Raw binary data.
@return string URL-safe base64 string without padding.
