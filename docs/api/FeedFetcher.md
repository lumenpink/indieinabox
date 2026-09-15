# FeedFetcher
**Namespace:** `Indieinabox`

FeedFetcher handles scheduling and fetching of subscribed external syndication feeds.

## Properties

### `private Indieinabox\Services\FetchFeedsService $service`

## Methods

### __construct()
`public function __construct(?Indieinabox\Services\FetchFeedsService $service = null)`

### fetchAll()
`public function fetchAll(): void`

Iterates through all channels and subscriptions, fetching new items for each.
