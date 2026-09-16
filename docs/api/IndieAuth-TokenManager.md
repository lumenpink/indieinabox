# TokenManager
**Namespace:** `Indieinabox\IndieAuth`

Class TokenManager

Manages generation, verification, and revocation of IndieAuth authorization codes
and bearer tokens with PKCE validation.

## Properties

### `private ?PDO $db`

@var ?PDO Database connection.

## Methods

### __construct()
`public function __construct(?PDO $db = null)`

TokenManager constructor.

@param ?PDO $db Optional database connection.

### getDb()
`public function getDb(): PDO`

### createAuthorizationCode()
`public function createAuthorizationCode(string $clientId, string $redirectUri, string $me, string $scope, ?string $codeChallenge = null, ?string $codeChallengeMethod = null, ?string $state = null, int $ttl = 600): string`

Creates an authorization code and stores its SHA-256 hash.

@param string $clientId Requesting client application identifier.
@param string $redirectUri Registered redirect URI.
@param string $me Canonical profile URL of the authenticated user.
@param string $scope Space-separated granted scopes.
@param ?string $codeChallenge Optional PKCE code challenge.
@param ?string $codeChallengeMethod Optional PKCE method ('S256' or 'plain').
@param int $ttl Lifetime in seconds (default 600s / 10 minutes).
@return string Plaintext authorization code.

### verifyAuthorizationCode()
`public function verifyAuthorizationCode(string $code, string $clientId, string $redirectUri, ?string $codeVerifier = null): array`

Verifies an authorization code (for authentication-only flows) and consumes it.

@param string $code Plaintext authorization code.
@param string $clientId Client ID verifying the code.
@param string $redirectUri Redirect URI.
@param ?string $codeVerifier Optional PKCE verifier.
@return array{me: string, scope: string}|array{error: string}

### exchangeCodeForToken()
`public function exchangeCodeForToken(string $code, string $clientId, string $redirectUri, ?string $codeVerifier = null): array`

Exchanges an authorization code for a long-lived Bearer access token and consumes the code.

@param string $code Plaintext authorization code.
@param string $clientId Client ID exchanging the code.
@param string $redirectUri Redirect URI.
@param ?string $codeVerifier Optional PKCE verifier.
@return array{access_token: string, me: string, scope: string, token_type: string}|array{error: string}

### validateBearerToken()
`public function validateBearerToken(?string $tokenOut = null, ?string $authHeader = null, ?string $queryToken = null, ?string $postToken = null): ?array`

Validates a provided Bearer token against stored valid tokens.

@param ?string $tokenOut Reference to the token string if found.
@param ?string $authHeader Optional raw Authorization header.
@param ?string $queryToken Optional access_token from query.
@param ?string $postToken Optional access_token from POST.
@return array{me: string, client_id: string, scope: string}|null

### fetchAndConsumeCode()
`private function fetchAndConsumeCode(string $code): ?array`

Fetches authorization code details and deletes it immediately from storage.

@param string $code
@return array<string, mixed>|null

### validateCodeParameters()
`private function validateCodeParameters(array $codeData, string $clientId, string $redirectUri, ?string $codeVerifier): ?string`

Validates expiration, client_id, redirect_uri, and PKCE parameters.

@param array<string, mixed> $codeData
@param string $clientId
@param string $redirectUri
@param ?string $codeVerifier
@return string|null Error message or null on success.
