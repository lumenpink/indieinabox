# AtomParser
**Namespace:** `Indieinabox\Feeds\Parsers`

Strategy parser for Atom feeds.

## Methods

### getFormat()
`public function getFormat(): string`

### supports()
`public function supports(string $content): bool`

### parse()
`public function parse(string $content, string $feedUrl): array`

@return array<int, array{
    uid: string,
    url: string,
    title: ?string,
    content: string,
    published_at: int,
    author: ?array<string, mixed>
}>
