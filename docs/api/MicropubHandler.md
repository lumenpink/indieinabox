# MicropubHandler
**Namespace:** `Indieinabox`

Class MicropubHandler

Orchestrates W3C Micropub API requests, validating authentication and delegating
queries, media uploads, and post creation to specialized handler classes.

## Properties

### `private Indieinabox\Site $site`

@var Site Global site configuration and environment.

### `private Indieinabox\IndieAuthHandler $authHandler`

@var IndieAuthHandler Authentication service.

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\IndieAuthHandler $authHandler = null)`

Initializes the MicropubHandler.

@param Site $site Global site configuration and environment.
@param ?IndieAuthHandler $authHandler Optional authentication handler.

### handle()
`public function handle(): void`

Main entry point for handling Micropub API requests.
Validates authentication and delegates to GET or POST specific handlers.

@return void

### getRawInput()
`protected function getRawInput(): string`

Reads the raw input stream. Used for parsing JSON payloads.

@return string The raw request body.

### handleGetRequest()
`private function handleGetRequest(): void`

Handles Micropub GET queries (e.g., config, source, syndicate-to).

@return void

### handlePostRequest()
`private function handlePostRequest(array $tokenData): void`

Handles Micropub POST requests for post creation.

@param array<string, mixed> $tokenData
@return void

### handleMediaEndpoint()
`private function handleMediaEndpoint(array $tokenData): void`

Handles file uploads to the Micropub media endpoint (/micropub/media).

@param array<string, mixed> $tokenData
@return void

### sendSuccessResponse()
`protected function sendSuccessResponse(int $code, array $headers = [], ?mixed $body = null): void`

Sends a successful HTTP response with headers.

@param int $code HTTP status code.
@param array<string, string> $headers Headers to include in the response.
@param mixed $body Optional body content.
@return void

### sendResponse()
`protected function sendResponse(int $code, string $error, string $description): void`

Sends a standard JSON-formatted HTTP error response.

@param int $code HTTP status code.
@param string $error Short error identifier.
@param string $description Detailed error message.
@return void

### moveUploadedFile()
`protected function moveUploadedFile(string $tmpName, string $destPath): bool`

Helper to move uploaded files to their destination.

@param string $tmpName Path of the uploaded temporary file.
@param string $destPath Final destination path.
@return bool True on success, false on failure.
