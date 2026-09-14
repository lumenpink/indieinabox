# WebmentionDiscovery
**Namespace:** `Indieinabox\BackgroundWorker`

Class WebmentionDiscovery

Scans remote domains queued in webmention_discovery_cache to determine
if they support receiving Webmentions.

## Properties

### `private Indieinabox\Site $site`

### `private PDO $db`

### `private array $callbacks`

@var array<string, callable>

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, PDO $db, array $callbacks = [])`

@param Site $site
@param PDO $db
@param array<string, callable> $callbacks Optional HTTP fetcher hooks

### process()
`public function process(): void`

Discovers Webmention support for queued domains.

@return void

### fetchUrl()
`protected function fetchUrl(string $url): string|false`

Fetches URL content using callback or standard stream context.

@param string $url
@return string|false
