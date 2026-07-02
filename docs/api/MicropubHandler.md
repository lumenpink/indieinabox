# MicropubHandler
**Namespace:** `Indieinabox`

Class MicropubHandler

## Properties

### `private Indieinabox\Site $site`

@var Indieinabox\Site

### `private Indieinabox\IndieAuthHandler $authHandler`

@var Indieinabox\IndieAuthHandler

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Method __construct
@param Indieinabox\Site $site

### handle()
`public function handle(): void`

Method handle
@return void

### getRawInput()
`protected function getRawInput(): string`

Method getRawInput
@return string

### handleGetRequest()
`private function handleGetRequest(): void`

Method handleGetRequest
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

Method sendSuccessResponse
@param int $code
@param array $headers
@param mixed $body

@return void

### sendResponse()
`protected function sendResponse(int $code, string $error, string $description): void`

Method sendResponse
@param int $code
@param string $error
@param string $description

@return void

### moveUploadedFile()
`protected function moveUploadedFile(string $tmpName, string $destPath): bool`

Method moveUploadedFile
@param string $tmpName
@param string $destPath

@return bool

### slugify()
`private function slugify(string $text): string`

Method slugify
@param string $text

@return string
