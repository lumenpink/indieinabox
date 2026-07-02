# IndieAuthHandler
**Namespace:** `Indieinabox`

## Properties

### `private Indieinabox\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

### handle()
`public function handle(): void`

### sendMetadata()
`private function sendMetadata(): void`

### handleAuthRequest()
`private function handleAuthRequest(): void`

### renderLoginForm()
`private function renderLoginForm(?string $error = null): void`

### processLogin()
`private function processLogin(): void`

### verifyAuthCode()
`private function verifyAuthCode(): void`

### handleTokenRequest()
`private function handleTokenRequest(): void`

### exchangeCodeForToken()
`private function exchangeCodeForToken(): void`

### validateBearerToken()
`public function validateBearerToken(?string $tokenOut = null): ?array`

### verifyToken()
`private function verifyToken(): void`

### sendResponse()
`private function sendResponse(int $code, string $message): void`
