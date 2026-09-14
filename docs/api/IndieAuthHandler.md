# IndieAuthHandler
**Namespace:** `Indieinabox`

Class IndieAuthHandler

Orchestrates IndieAuth and OAuth 2.0 endpoints (Metadata discovery, Authorization, Token exchange)
delegating cryptographic PKCE verification to PkceValidator, lifecycle storage to TokenManager,
and presentation templates to ConsentView.

## Properties

### `private Indieinabox\Site $site`

@var Site Global site configuration and environment.

### `private Indieinabox\IndieAuth\TokenManager $tokenManager`

@var TokenManager Token and code management service.

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\IndieAuth\TokenManager $tokenManager = null)`

Initializes the IndieAuthHandler and binds dependencies.

@param Site $site Global site configuration.
@param ?TokenManager $tokenManager Optional token manager service.

### handle()
`public function handle(): void`

Main entry point for IndieAuth requests.
Routes the request to metadata, token exchange, or authorization endpoints.

@return void

### sendMetadata()
`private function sendMetadata(): void`

Sends the OAuth 2.0 Authorization Server Metadata (JSON).

@return void

### handleAuthRequest()
`private function handleAuthRequest(): void`

Handles the authorization endpoint (`/auth`).

@return void

### processLogin()
`private function processLogin(): void`

Processes submission of the user login form and issues an authorization code.

@return void

### verifyAuthCode()
`private function verifyAuthCode(): void`

Verifies an authorization code submitted by the client application.

@return void

### handleTokenRequest()
`private function handleTokenRequest(): void`

Handles requests to the token endpoint (`/token`).

@return void

### exchangeCodeForToken()
`private function exchangeCodeForToken(): void`

Exchanges an authorization code for a Bearer access token.

@return void

### validateBearerToken()
`public function validateBearerToken(?string $tokenOut = null): ?array`

Validates a provided Bearer token against stored valid tokens.

@param ?string $tokenOut Reference to the token string if found.
@return array{me: string, client_id: string, scope: string}|null

### verifyToken()
`private function verifyToken(): void`

Verifies the provided token via a GET request to the token endpoint.

@return void
