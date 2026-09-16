# ActivityPubAdapter
**Namespace:** `Indieinabox\Federation`

Protocol adapter implementing W3C ActivityPub / ActivityStreams 2.0 federation.

## Properties

### `private Indieinabox\Site\Site $site`

### `private mixed $transport`

@var callable|null

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, ?callable $transport = null)`

@param Site $site
@param callable|null $transport Optional HTTP client hook: fn(string $url, array $headers, string $body): array{code: int, body: string, error: string}

### getProtocol()
`public function getProtocol(): string`

### supports()
`public function supports(string $protocol): bool`

### buildLikeActivity()
`public function buildLikeActivity(string $targetUrl): array`

### buildReplyActivity()
`public function buildReplyActivity(string $targetUrl, string $content, ?string $inReplyTo = null): array`

### buildFollowActivity()
`public function buildFollowActivity(string $targetActorUri): array`

### deliverActivity()
`public function deliverActivity(array|string $activity, string $destinationUrl): bool`

### parseActivity()
`public function parseActivity(string $payload): ?array`

### getFqdn()
`private function getFqdn(): string`

### getActorUri()
`private function getActorUri(): string`

### sendCurl()
`private function sendCurl(string $targetUrl, array $headers, string $payload): bool`

Sends payload via standard cURL with HTTP signature headers.

@param string $targetUrl
@param array<string, string> $headers
@param string $payload
@return bool
