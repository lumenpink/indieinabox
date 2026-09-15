# FeedParserInterface
**Namespace:** `Indieinabox\Feeds\Contracts`

Strategy contract for parsing incoming syndication and social feeds.

## Methods

### getFormat()
`abstract public function getFormat(): string`

Returns the format identifier (e.g. 'rss', 'atom', 'twtxt', 'jsonfeed', 'activitypub').

### supports()
`abstract public function supports(string $content): bool`

Checks if this parser can handle the given raw feed content.

### parse()
`abstract public function parse(string $content, string $feedUrl): array`

Parses the feed content into a list of normalized item arrays.

@param string $content
@param string $feedUrl
@return array<int, array{
    uid: string,
    url: string,
    title: ?string,
    content: string,
    published_at: int,
    author: ?array<string, mixed>
}>
