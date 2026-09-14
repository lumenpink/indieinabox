# WebmentionHandler
**Namespace:** `Indieinabox`

Class WebmentionHandler

## Properties

### `private Indieinabox\Site $site`

@var \Indieinabox\Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Initializes the WebmentionHandler.

@param \Indieinabox\Site $site Global site configuration and environment.

### handle()
`public function handle(): void`

Processes incoming webmentions via POST requests.
Validates source/target URIs, downloads the source content,
discovers microformats (h-entry), and stores it for moderation.

@return void

### verifySourceLink()
`public function verifySourceLink(string $source, string $target): array`

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

Compare target and link href to check if they match (including relative links)

@param string $href
@param string $target
@param string $source
@return bool

### queueWebmention()
`public function queueWebmention(string $source, string $target): void`

@param string $source
@param string $target

### sendResponse()
`private function sendResponse(int $code, string $message): void`

Sends a plain-text HTTP response with a specific status code.

@param int $code HTTP status code.
@param string $message Response message.
@return void

### sendHelpPage()
`private function sendHelpPage(): void`

Renders a basic HTML help page for the webmention endpoint (used on GET requests).

@return void
