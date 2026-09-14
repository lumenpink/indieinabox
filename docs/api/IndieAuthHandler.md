# IndieAuthHandler
**Namespace:** `Indieinabox`

Class IndieAuthHandler

## Properties

### `private Indieinabox\Site $site`

@var \Indieinabox\Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Initializes the IndieAuthHandler.

@param \Indieinabox\Site $site Global site configuration and environment.

### handle()
`public function handle(): void`

Main entry point for IndieAuth requests.
Routes the request to metadata, token exchange, or authorization endpoints
based on the URL path.

@return void

### sendMetadata()
`private function sendMetadata(): void`

Sends the OAuth 2.0 Authorization Server Metadata (JSON).
Used by clients to discover the endpoints and supported features of this IndieAuth provider.

@return void

### handleAuthRequest()
`private function handleAuthRequest(): void`

Handles the authorization endpoint (`/auth`).
Renders the login form for GET requests, and processes login submissions
or authorization code verifications for POST requests.

@return void

### renderLoginForm()
`private function renderLoginForm(?string $error = null): void`

Renders the HTML login form for the authorization flow.
Displays details about the requesting client application (client_id, scope).

@param string|null $error Optional error message to display on the form.
@return void

### processLogin()
`private function processLogin(): void`

Processes the submission of the login form.
Validates the password, generates a temporary authorization code,
and redirects the user back to the client application's redirect URI.

@return void

### verifyAuthCode()
`private function verifyAuthCode(): void`

Verifies the authorization code exchanged by the client application.
Validates the code, redirect URI, client ID, and PKCE challenge (if present),
returning the authenticated user profile in JSON format upon success.

@return void

### handleTokenRequest()
`private function handleTokenRequest(): void`

Handles requests to the token endpoint (`/token`).
Supports POST requests to exchange an authorization code for an access token,
or GET requests to verify a token.

@return void

### exchangeCodeForToken()
`private function exchangeCodeForToken(): void`

Exchanges an authorization code for a Bearer access token.
Validates the code and PKCE parameters, then generates a long-lived access token
and stores it for the authenticated user/client.

@return void

### validateBearerToken()
`public function validateBearerToken(?string $tokenOut = null): ?array`

Validates a provided Bearer token against stored valid tokens.

@param ?string $tokenOut Reference to the token string if found.
@return ?array Array containing token details (me, client_id, scope) or null if invalid.

### verifyToken()
`private function verifyToken(): void`

Verifies the provided token (e.g., via a GET request to the token endpoint).
Returns the token details (me, client_id, scope) if valid.

@return void

### sendResponse()
`private function sendResponse(int $code, string $message): void`

Sends a JSON-formatted HTTP response with a specific status code.

@param int $code HTTP status code.
@param string $message Response message.
@return void
