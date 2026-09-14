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

Iterates through all channels and subscriptions, fetching new items for each.

@return void

### fetchSubscription()
`private function fetchSubscription(string $channel, string $url): void`

Fetches and parses a single subscription URL.
Automatically detects the feed format (JSON Feed, RSS, Atom, Twtxt).

@param string $channel The Microsub channel ID (e.g., 'timeline').
@param string $url The subscription URL to fetch.
@return void

### parseTwtxt()
`private function parseTwtxt(string $channel, string $feedUrl, string $content): void`

Parses a Twtxt format feed and saves new entries.

@param string $channel The Microsub channel ID.
@param string $feedUrl The source URL.
@param string $content The raw Twtxt feed content.
@return void

### parseJsonFeed()
`private function parseJsonFeed(string $channel, string $feedUrl, array $json): void`

Parses a JSON Feed and saves new entries.

@param string $channel The Microsub channel ID.
@param string $feedUrl The source URL.
@param array $json The parsed JSON Feed data.
@return void

### parseActivityPub()
`private function parseActivityPub(string $channel, string $feedUrl, array $json): void`

Parses an ActivityPub Actor profile and fetches their outbox.

@param string $channel The Microsub channel ID.
@param string $feedUrl The source URL.
@param array $json The parsed JSON ActivityPub data.
@return void

### parseRss()
`private function parseRss(string $channel, string $feedUrl, SimpleXMLElement $xml): void`

Parses an RSS feed and saves new entries.

@param string $channel The Microsub channel ID.
@param string $feedUrl The source URL.
@param SimpleXMLElement $xml The parsed XML.

@return void

### parseAtom()
`private function parseAtom(string $channel, string $feedUrl, SimpleXMLElement $xml): void`

Parses an Atom feed.

@param string $channel The Microsub channel ID.
@param string $feedUrl The source URL.
@param SimpleXMLElement $xml The parsed XML.

@return void

### itemExists()
`private function itemExists(string $id, string $channel): bool`

### saveEntry()
`private function saveEntry(Indieinabox\Microsub\ExtendedEntry $entry, string $channel, string $feedUrl = ''): void`

Saves a parsed feed entry to the local file system (Microsub item store).

@param \Indieinabox\Microsub\ExtendedEntry $entry The universal post object.
@param string $channel The channel ID where the item belongs.
@param string $feedUrl The URL of the feed this item belongs to.

@return void

### fetchApJson()
`private function fetchApJson(string $url, mixed $fallbackCtx = null)`

Fetches ActivityPub JSON, automatically attempting HTTP Signatures if available.
Uses the provided stream context as a fallback if signing fails or is not possible.

### processHtmlMedia()
`private function processHtmlMedia(string $html): string`

Replaces remote media URLs in HTML content with local cached URLs.

### downloadMedia()
`private function downloadMedia(string $url, string $type): string`

Downloads a media file locally.
