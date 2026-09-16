# FetchFeedsService
**Namespace:** `Indieinabox\Services`

Domain service responsible for fetching, parsing, and storing external feed subscriptions.

## Properties

### `private PDO $db`

### `private Indieinabox\Repositories\Contracts\SettingsRepositoryInterface $settings`

### `private array $parsers`

@var array<string, FeedParserInterface>

## Methods

### __construct()
`public function __construct(?PDO $db = null, ?array $parsers = null, ?Indieinabox\Repositories\Contracts\SettingsRepositoryInterface $settings = null)`

@param ?PDO $db
@param array<int, FeedParserInterface>|null $parsers
@param ?SettingsRepositoryInterface $settings

### addParser()
`public function addParser(Indieinabox\Feeds\Contracts\FeedParserInterface $parser): Indieinabox\Services\FetchFeedsService`

### getParsers()
`public function getParsers(): array`

@return array<string, FeedParserInterface>

### findParser()
`public function findParser(string $content): ?Indieinabox\Feeds\Contracts\FeedParserInterface`

### fetchAll()
`public function fetchAll(): int`

Fetches all registered subscriptions across all channels.

@return int Number of subscriptions processed.

### fetchSubscription()
`public function fetchSubscription(string $channel, string $url, ?string $rawContent = null): int`

Fetches and parses a single subscription URL.

@param string $channel Microsub channel ID (e.g., 'inbox').
@param string $url Target subscription URL.
@param string|null $rawContent Optional content override for testing or offline parsing.
@return int Number of items parsed and saved.

### parseActivityPub()
`private function parseActivityPub(string $channel, string $feedUrl, array $json): int`

Parses an ActivityPub Actor profile and fetches outbox items.

@param string $channel
@param string $feedUrl
@param array<string, mixed> $json
@return int

### itemExists()
`public function itemExists(string $id, string $channel): bool`

### saveEntry()
`public function saveEntry(Indieinabox\Microsub\ExtendedEntry $entry, string $channel, string $feedUrl = ''): bool`

### processHtmlMedia()
`public function processHtmlMedia(string $html): string`

### downloadMedia()
`public function downloadMedia(string $url, string $type): string`

### fetchUrl()
`protected function fetchUrl(string $url, mixed $context = null)`

### fetchApJson()
`private function fetchApJson(string $url, mixed $fallbackCtx = null)`
