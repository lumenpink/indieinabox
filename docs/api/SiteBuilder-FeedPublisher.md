# FeedPublisher
**Namespace:** `Indieinabox\SiteBuilder`

Orchestrates generation and publishing of feeds across all languages and protocols.

## Properties

### `private Indieinabox\Site $site`

### `private array $generators`

/** @var FeedGeneratorInterface[] */

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?array $generators = null)`

@param Site $site
@param FeedGeneratorInterface[]|null $generators

### publishFeeds()
`public function publishFeeds(Indieinabox\Pages $pages): void`

Publishes feeds for all configured languages from the Pages collection.
