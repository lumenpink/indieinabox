# IndieAuthController
**Namespace:** `Indieinabox\Http\Controllers`

Controller handling IndieAuth authentication, authorization code exchange, token issues, and metadata.

## Properties

### `private Indieinabox\IndieAuth\TokenManager $tokenManager`

### `protected Indieinabox\Site\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, ?Indieinabox\IndieAuth\TokenManager $tokenManager = null)`

### getTokenManager()
`public function getTokenManager(): Indieinabox\IndieAuth\TokenManager`

### handle()
`public function handle(): void`

Dispatches IndieAuth request.

### sendMetadata()
`public function sendMetadata(): void`

Sends the OAuth 2.0 Authorization Server Metadata (JSON).

### handleAuthRequest()
`public function handleAuthRequest(): void`

Handles the authorization endpoint (`/auth`).

### processLogin()
`public function processLogin(): void`

Processes submission of the user login form and issues an authorization code.

### verifyAuthCode()
`public function verifyAuthCode(): void`

Verifies an authorization code submitted by the client application.

### handleTokenRequest()
`public function handleTokenRequest(): void`

Handles requests to the token endpoint (`/token`).

### exchangeCodeForToken()
`public function exchangeCodeForToken(): void`

Exchanges an authorization code for a Bearer access token.

### validateBearerToken()
`public function validateBearerToken(?string $tokenOut = null): ?array`

Validates a provided Bearer token against stored valid tokens.

@param ?string $tokenOut Reference to the token string if found.
@return array{me: string, client_id: string, scope: string}|null

### verifyToken()
`public function verifyToken(): void`

Verifies the provided token via a GET request to the token endpoint.

### getSite()
`public function getSite(): Indieinabox\Site\Site`

### json()
`protected function json(?mixed $data, int $status = 200, array $headers = []): void`

Emits a JSON response.

@param mixed $data
@param int $status
@param array<string, string> $headers

### html()
`protected function html(string $html, int $status = 200, array $headers = []): void`

Emits an HTML response.

@param string $html
@param int $status
@param array<string, string> $headers

### redirect()
`protected function redirect(string $url, int $status = 302): void`

Emits a redirect header.

### status()
`protected function status(int $status): void`

Sets HTTP status code.

### jsonResponse()
`protected function jsonResponse(?mixed $data, int $status = 200, array $headers = []): void`

@param array<string, string> $headers

### htmlResponse()
`protected function htmlResponse(string $html, int $status = 200, array $headers = []): void`

@param array<string, string> $headers

### redirectResponse()
`protected function redirectResponse(string $url, int $status = 302): void`
