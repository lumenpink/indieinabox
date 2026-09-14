# LinkExtractor
**Namespace:** `Indieinabox\Webmention`

Class LinkExtractor

Extracts and filters outbound links from post frontmatter and content for Webmention dispatching.

## Methods

### extractLinks()
`public static function extractLinks(string $sourceUrl, array $frontmatter, string $content): array`

Extracts, deduplicates, and filters outgoing URLs from frontmatter and content.
Automatically filters out self-pings matching the source host.

@param string $sourceUrl The permalink of the local post.
@param array<string, mixed> $frontmatter Post frontmatter metadata.
@param string $content Post Markdown or HTML body.
@return string[] Array of unique, valid target URLs.

### extractFromFrontmatter()
`public static function extractFromFrontmatter(array $frontmatter): array`

Extracts URLs defined in standard frontmatter interaction properties.

@param array<string, mixed> $frontmatter
@return string[]

### extractFromContent()
`public static function extractFromContent(string $content): array`

Extracts URLs from Markdown links, HTML href attributes, and bare URLs in text.

@param string $content
@return string[]

### filterSelfPings()
`public static function filterSelfPings(string $sourceUrl, array $links): array`

Filters out target URLs that belong to the same host as the source URL (self-pings).

@param string $sourceUrl
@param string[] $links
@return string[]
