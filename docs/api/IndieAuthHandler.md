# IndieAuthHandler
**Namespace:** `Indieinabox`

Class IndieAuthHandler

## Properties

### `private Indieinabox\Site $site`

@var Indieinabox\Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Method __construct
@param Indieinabox\Site $site

### handle()
`public function handle(): void`

Method handle
@return void

### sendMetadata()
`private function sendMetadata(): void`

Method sendMetadata
@return void

### handleAuthRequest()
`private function handleAuthRequest(): void`

Method handleAuthRequest
@return void

### renderLoginForm()
`private function renderLoginForm(?string $error = null): void`

Method renderLoginForm
@param ?string $error

@return void

### processLogin()
`private function processLogin(): void`

Method processLogin
@return void

### verifyAuthCode()
`private function verifyAuthCode(): void`

Method verifyAuthCode
@return void

### handleTokenRequest()
`private function handleTokenRequest(): void`

Method handleTokenRequest
@return void

### exchangeCodeForToken()
`private function exchangeCodeForToken(): void`

Method exchangeCodeForToken
@return void

### validateBearerToken()
`public function validateBearerToken(?string $tokenOut = null): ?array`

Method validateBearerToken
@param ?string $tokenOut

@return ?array

### verifyToken()
`private function verifyToken(): void`

Method verifyToken
@return void

### sendResponse()
`private function sendResponse(int $code, string $message): void`

Method sendResponse
@param int $code
@param string $message

@return void
