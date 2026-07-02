# HttpSignature
**Namespace:** `Indieinabox`

Class HttpSignature

## Methods

### sign()
`public static function sign(string $keyId, string $privateKey, string $method, string $url, string $body = '', array $additionalHeaders = []): array`

Signs a request for ActivityPub.

@param string $keyId The URL to the public key (Actor profile ID#main-key)
@param string $privateKey The PEM formatted RSA private key
@param string $method HTTP Method (POST, GET)
@param string $url Target URL
@param string $body Request body (if any)
@param array $additionalHeaders Associative array of additional headers to include in signature
@return array Array containing headers to add to the request (Date, Host, Digest, Signature)

### verify()
`public static function verify(array $headers, string $method, string $path, string $publicKey): bool`

Verifies an incoming HTTP Signature.

@param array $headers The incoming HTTP headers
@param string $method Request method
@param string $path Request path with query string
@param string $publicKey PEM formatted RSA public key
@return bool True if valid
