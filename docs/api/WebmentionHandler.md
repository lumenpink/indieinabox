# WebmentionHandler
**Namespace:** `Indieinabox`

## Properties

### `private Indieinabox\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

### handle()
`public function handle(): void`

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

### sendHelpPage()
`private function sendHelpPage(): void`
