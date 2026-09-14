# WebmentionHandler
**Namespace:** `Indieinabox`

Class WebmentionHandler

Handles incoming Webmention requests, validates endpoints, queues mentions,
and delegates source verification and view rendering to dedicated services.

## Properties

### `private Indieinabox\Site $site`

@var Site Global site configuration and environment.

### `private Indieinabox\Webmention\SourceVerifier $sourceVerifier`

@var SourceVerifier Source link verification service.

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\Webmention\SourceVerifier $sourceVerifier = null)`

Initializes the WebmentionHandler.

@param Site $site Global site configuration and environment.
@param ?SourceVerifier $sourceVerifier Optional custom source verifier.

### handle()
`public function handle(): void`

Processes incoming webmentions via POST requests.
Validates source/target URIs, checks target file existence, and queues for moderation.
Renders help form on GET requests.

@return void

### verifySourceLink()
`public function verifySourceLink(string $source, string $target): array`

Verifies that the source URL contains a link to target URL and extracts metadata.

@param string $source
@param string $target
@return array{success: bool, message?: string, content?: array{title: string, text: string, whostyle?: array<array-key, mixed>|null}}

### fetchUrl()
`protected function fetchUrl(string $url)`

Allows overriding the URL fetcher for test mocking.

@param string $url
@return string|false

### urlsMatch()
`public function urlsMatch(string $href, string $target, string $source): bool`

Compares target and link href to check if they match (including relative links).

@param string $href
@param string $target
@param string $source
@return bool

### queueWebmention()
`public function queueWebmention(string $source, string $target): void`

Queues a webmention in inbox_queue for asynchronous background worker processing.

@param string $source
@param string $target
@return void

### sendResponse()
`private function sendResponse(int $code, string $message): void`

Sends a JSON HTTP response with a specific status code.

@param int $code HTTP status code.
@param string $message Response message.
@return void

### sendHelpPage()
`private function sendHelpPage(): void`

Renders HTML help page for the webmention endpoint (used on GET requests).

@return void
