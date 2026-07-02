# FeedFetcher
**Namespace:** `Indieinabox`

## Properties

### `private PDO $db`

## Methods

### __construct()
`public function __construct()`

### fetchAll()
`public function fetchAll(): void`

### fetchSubscription()
`private function fetchSubscription(string $channel, string $url): void`

### parseTwtxt()
`private function parseTwtxt(string $channel, string $feedUrl, string $content): void`

### parseJsonFeed()
`private function parseJsonFeed(string $channel, string $feedUrl, array $json): void`

### parseRss()
`private function parseRss(string $channel, string $feedUrl, SimpleXMLElement $xml): void`

### parseAtom()
`private function parseAtom(string $channel, string $feedUrl, SimpleXMLElement $xml): void`

### saveItem()
`private function saveItem(string $id, string $channel, string $url, string $content, int $published, string $authorName, string $authorPhoto): void`
