# PayloadParser
**Namespace:** `Indieinabox\Webmention`

Class PayloadParser

Parses remote HTML payloads using Microformats 2 (mf2) to extract rich IndieWeb metadata
(author h-card, photo, name, interaction types, and sanitized content).

## Methods

### parse()
`public static function parse(string $html, string $sourceUrl, ?string $targetUrl = null): array`

Parses source HTML and extracts normalized interaction and author metadata.

@param string $html The fetched source HTML.
@param string $sourceUrl The source URL for relative reference resolution.
@param ?string $targetUrl The target URL to match interaction properties against.
@return array{
    title: string,
    text: string,
    html: string,
    author_name: string,
    author_photo: string,
    author_url: string,
    interaction_type: string,
    rsvp: ?string,
    published: ?string,
    whostyle: ?array<array-key, mixed>
}

### findItemByType()
`private static function findItemByType(array $items, string $type): ?array`

Finds the first microformat item matching a given type prefix.

@param array<int, mixed> $items
@param string $type
@return array<string, mixed>|null

### extractAuthor()
`public static function extractAuthor(?array $entry, array $items, string $sourceUrl): array`

Extracts author details (name, photo, URL) from entry or top-level h-cards.

@param array<string, mixed>|null $entry
@param array<int, mixed> $items
@param string $sourceUrl
@return array{name: string, photo: string, url: string}

### extractTitle()
`private static function extractTitle(?array $entry, string $html): string`

Extracts title from entry properties or HTML <title> tag.

@param array<string, mixed>|null $entry
@param string $html
@return string

### extractContent()
`private static function extractContent(?array $entry, string $html): array`

Extracts text and HTML content from entry e-content or DOM fallback.

@param array<string, mixed>|null $entry
@param string $html
@return array{text: string, html: string}

### detectInteractionType()
`public static function detectInteractionType(?array $entry, ?string $targetUrl, string $sourceUrl): array`

Detects IndieWeb interaction type (like, repost, reply, bookmark, rsvp, or webmention).

@param array<string, mixed>|null $entry
@param ?string $targetUrl
@param string $sourceUrl
@return array{type: string, rsvp: ?string}
