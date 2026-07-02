# MicropubHandler
**Namespace:** `Indieinabox`

## Properties

### `private Indieinabox\Site $site`

### `private Indieinabox\IndieAuthHandler $authHandler`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

### handle()
`public function handle(): void`

### getRawInput()
`protected function getRawInput(): string`

### handleGetRequest()
`private function handleGetRequest(): void`

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

### sendResponse()
`protected function sendResponse(int $code, string $error, string $description): void`

### moveUploadedFile()
`protected function moveUploadedFile(string $tmpName, string $destPath): bool`

### slugify()
`private function slugify(string $text): string`
