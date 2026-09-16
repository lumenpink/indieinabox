# WebmentionDiscovery
**Namespace:** `Indieinabox\BackgroundWorker`

Class WebmentionDiscovery

Scans remote domains queued in webmention_discovery_cache to determine
if they support receiving Webmentions.

## Properties

### `private Indieinabox\Site\Site $site`

### `private PDO $db`

### `private mixed $fetcher`

@var callable|null

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, PDO $db, ?callable $fetcher = null)`

@param Site $site
@param PDO $db
@param callable|null $fetcher Optional HTTP fetcher hook fn(string $url): string|false

### process()
`public function process(): void`

Discovers Webmention support for queued domains.

@return void

### fetchUrl()
`public function fetchUrl(string $url)`

Fetches remote content over HTTP.

@param string $url
@return string|false
