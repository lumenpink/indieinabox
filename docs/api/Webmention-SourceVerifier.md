# SourceVerifier
**Namespace:** `Indieinabox\Webmention`

Class SourceVerifier

Verifies that a remote source page links back to a local target URL,
and extracts microformats / metadata (title, e-content, Whostyles).

## Properties

### `private mixed $fetcher`

@var callable|null Custom URL fetcher callable.

## Methods

### __construct()
`public function __construct(?callable $fetcher = null)`

SourceVerifier constructor.

@param ?callable $fetcher Optional callback to fetch remote URLs: fn(string $url): string|false.

### verifySourceLink()
`public function verifySourceLink(string $source, string $target): array`

Verifies that the source URL contains a link to target URL and extracts metadata.

@param string $source
@param string $target

@return ((array|null|string)[]|bool|string)[]

@psalm-return array{success: bool, content?: array{title: string, text: string, html: string, author_name: string, author_photo: string, author_url: string, interaction_type: string, rsvp: null|string, whostyle: array|null}, message?: 'No link to target URL found on source page.'|'Unable to fetch source URL.'}

### urlsMatch()
`public function urlsMatch(string $href, string $target, string $source): bool`

Compares target and link href to check if they match, including relative links.

@param string $href
@param string $target
@param string $source
@return bool

### normalizeUrl()
`public function normalizeUrl(string $url): string`

Normalizes a URL for canonical matching (resolves path segments and trailing slashes).

@param string $url
@return string

### fetchUrl()
`public function fetchUrl(string $url): string|false`

Fetches remote URL content.

@param string $url
@return string|false
