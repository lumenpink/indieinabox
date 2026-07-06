# WebmentionHandler
**Namespace:** `Indieinabox`

Class WebmentionHandler

## Properties

### `private Indieinabox\Site $site`

@var \Indieinabox\Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Method __construct
@param \Indieinabox\Site $site

### handle()
`public function handle(): void`

Method handle
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

Method sendResponse
@param int $code
@param string $message

@return void

### sendHelpPage()
`private function sendHelpPage(): void`

Method sendHelpPage
@return void
