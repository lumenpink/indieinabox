# MicropubController
**Namespace:** `Indieinabox\Http\Controllers`

Controller handling Micropub server queries, post creation, media uploads, and the web-based posting client.

## Properties

### `private Indieinabox\IndieAuth\TokenManager $tokenManager`

### `private Indieinabox\Commands\Contracts\CommandBusInterface $commandBus`

### `protected Indieinabox\Site\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, ?Indieinabox\IndieAuth\TokenManager $tokenManager = null, ?Indieinabox\Commands\Contracts\CommandBusInterface $commandBus = null)`

### handle()
`public function handle(): void`

Handles standard Micropub endpoint requests (POST create/media, GET config/syndicate-to).

### client()
`public function client(): void`

Handles the Micropub local web admin posting client.

### handleGetRequest()
`protected function handleGetRequest(): void`

### handlePostRequest()
`protected function handlePostRequest(array $tokenData): void`

@param array<string, mixed> $tokenData

### handleMediaEndpoint()
`protected function handleMediaEndpoint(array $tokenData): void`

@param array<string, mixed> $tokenData

### getRawInput()
`protected function getRawInput(): string`

### moveUploadedFile()
`protected function moveUploadedFile(string $tmpName, string $destPath): bool`

### sendSuccessResponse()
`protected function sendSuccessResponse(int $code, array $headers = [], ?mixed $body = null): void`

@param int $code
@param array<string, string> $headers
@param mixed $body

### sendErrorResponse()
`protected function sendErrorResponse(int $code, string $error, string $description): void`

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
`protected function jsonResponse(array $data, int $status = 200, array $headers = []): void`

@param array<string, string> $headers
@param (int|string|string[])[] $data

@psalm-param array{error?: string, error_description?: string, issuer?: string, authorization_endpoint?: string, token_endpoint?: string, response_types_supported?: list{'code'}, grant_types_supported?: list{'authorization_code'}, code_challenge_methods_supported?: list{'S256', 'plain'}, me?: string, scope?: string, access_token?: string, token_type?: string, client_id?: string, status?: int, message?: string} $data

### htmlResponse()
`protected function htmlResponse(string $html, int $status = 200, array $headers = []): void`

@param array<string, string> $headers

### redirectResponse()
`protected function redirectResponse(string $url, int $status = 302): void`
