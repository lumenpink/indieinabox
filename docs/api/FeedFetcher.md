# FeedFetcher
**Namespace:** `Indieinabox`

Class FeedFetcher

## Properties

### `private PDO $db`

@var PDO

## Methods

### __construct()
`public function __construct()`

Method __construct

### fetchAll()
`public function fetchAll(): void`

Method fetchAll
@return void

### fetchSubscription()
`private function fetchSubscription(string $channel, string $url): void`

Method fetchSubscription
@param string $channel
@param string $url

@return void

### parseTwtxt()
`private function parseTwtxt(string $channel, string $feedUrl, string $content): void`

Method parseTwtxt
@param string $channel
@param string $feedUrl
@param string $content

@return void

### parseJsonFeed()
`private function parseJsonFeed(string $channel, string $feedUrl, array $json): void`

Method parseJsonFeed
@param string $channel
@param string $feedUrl
@param array $json

@return void

### parseRss()
`private function parseRss(string $channel, string $feedUrl, SimpleXMLElement $xml): void`

Method parseRss
@param string $channel
@param string $feedUrl
@param SimpleXMLElement $xml

@return void

### parseAtom()
`private function parseAtom(string $channel, string $feedUrl, SimpleXMLElement $xml): void`

Method parseAtom
@param string $channel
@param string $feedUrl
@param SimpleXMLElement $xml

@return void

### saveItem()
`private function saveItem(string $id, string $channel, string $url, string $content, int $published, string $authorName, string $authorPhoto): void`

Method saveItem
@param string $id
@param string $channel
@param string $url
@param string $content
@param int $published
@param string $authorName
@param string $authorPhoto

@return void
