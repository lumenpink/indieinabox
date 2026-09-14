# MicropubHandler
**Namespace:** `Indieinabox`

Class MicropubHandler

## Properties

### `private Indieinabox\Site $site`

@var \Indieinabox\Site

### `private Indieinabox\IndieAuthHandler $authHandler`

@var \Indieinabox\IndieAuthHandler

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Initializes the MicropubHandler.

@param \Indieinabox\Site $site Global site configuration and environment.

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
Returns JSON configurations or existing post data.

@return void

### handlePostRequest()
`private function handlePostRequest(array $tokenData): void`

@param array<string, mixed> $tokenData

### createPost()
`private function createPost(array $input): void`

@param array<string, mixed> $input

### handleMediaEndpoint()
`private function handleMediaEndpoint(array $tokenData): void`

@param array<string, mixed> $tokenData

### sendSuccessResponse()
`protected function sendSuccessResponse(int $code, array $headers = [], mixed $body = null): void`

Sends a successful HTTP response, typically indicating creation (201 or 202).
Includes a Location header for newly created resources.

@param int $code HTTP status code.
@param array $headers Headers to include in the response.
@param mixed $body Optional body content.

@return void

### sendResponse()
`protected function sendResponse(int $code, string $error, string $description): void`

Sends a standard JSON-formatted HTTP error response.

@param int $code HTTP status code.
@param string $error Short error identifier (e.g., 'invalid_request').
@param string $description Detailed error message.
@return void

### moveUploadedFile()
`protected function moveUploadedFile(string $tmpName, string $destPath): bool`

Helper to move uploaded files to their destination.

@param string $tmpName Path of the uploaded temporary file.
@param string $destPath Final destination path.
@return bool True on success, false on failure.

### slugify()
`private function slugify(string $text): string`

Converts a string into a URL-friendly slug.

@param string $text The text to slugify.
@return string The resulting slug.
