# TwtxtManager
**Namespace:** `Indieinabox\Twtxt`

Class TwtxtManager

## Methods

### cleanMessage()
`public static function cleanMessage(string $text): string`

Cleans a message by stripping Markdown formatting and collapsing it to a single line.

@param string $text
@return string

### formatMessageToHtml()
`public static function formatMessageToHtml(string $message): string`

Converts raw message text into HTML with mentions, hashtags, and links formatted.

@param string $message
@return string

### parseFeedContent()
`public static function parseFeedContent(string $content, string $defaultNick, ?string $sourceUrl = null): array`

Parses a twtxt feed string into universal Entry objects.

@param string $content
@param string $defaultNick
@param string|null $sourceUrl
@return Entry[]

### fetchTimeline()
`public function fetchTimeline(array $following, string $cacheDir, bool $fetchOnline = false): array`

Fetches timeline updates from remote feeds.

@param array<int, array<string, string>> $following
@param string $cacheDir
@param bool $fetchOnline If false, only reads from local cache.
@return Entry[]

### fetchHubMentions()
`public function fetchHubMentions(array $hubs, string $fqdn, string $cacheDir, bool $fetchOnline = false): array`

Queries all configured hubs to fetch replies/mentions.

@param array<int, string> $hubs
@param string $fqdn
@param string $cacheDir
@param bool $fetchOnline If false, only reads from local cache.
@return Entry[]

### fetchUrl()
`private static function fetchUrl(string $url): string|false`

Helper to perform high-tolerance HTTP requests.

@param string $url
@return string|false
