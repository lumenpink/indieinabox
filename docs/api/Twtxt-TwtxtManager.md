# TwtxtManager
**Namespace:** `Indieinabox\Twtxt`

Class TwtxtManager

## Methods

### cleanMessage()
`public static function cleanMessage(string $text): string`

Cleans a message by stripping Markdown formatting and collapsing it to a single line.

@param string $text
@return string

### formatPageToTwtxtMessage()
`public static function formatPageToTwtxtMessage(Indieinabox\Page $page, string $fqdn): string`

Formats a Page object content into a twtxt message based on its kind.

@param Page $page
@param string $fqdn
@return string

### generateFeed()
`public function generateFeed(array $pages, string $outputFile, string $fqdn, Indieinabox\Site\Twtxt $config): void`

Generates a twtxt.txt feed and writes it to the output file.

@param Page[] $pages
@param string $outputFile
@param string $fqdn
@param TwtxtConfig $config
@return void

### formatMessageToHtml()
`public static function formatMessageToHtml(string $message): string`

Converts raw message text into HTML with mentions, hashtags, and links formatted.

@param string $message
@return string

### parseFeedContent()
`public static function parseFeedContent(string $content, string $defaultNick): array`

Parses a twtxt feed string into structured TwtxtEntry objects.

@param string $content
@param string $defaultNick
@return TwtxtEntry[]

### fetchTimeline()
`public function fetchTimeline(array $following, string $cacheDir): array`

Fetches timeline updates from remote feeds.

@param array<int, array<string, string>> $following
@param string $cacheDir
@return TwtxtEntry[]

### fetchHubMentions()
`public function fetchHubMentions(array $hubs, string $fqdn): array`

Queries all configured hubs to fetch replies/mentions.

@param array<int, string> $hubs
@param string $fqdn
@return TwtxtEntry[]

### fetchUrl()
`private static function fetchUrl(string $url): string|false`

Helper to perform high-tolerance HTTP requests.

@param string $url
@return string|false
