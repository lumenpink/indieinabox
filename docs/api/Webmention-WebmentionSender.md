# WebmentionSender
**Namespace:** `Indieinabox\Webmention`

Class WebmentionSender

Scans newly published or modified content for outbound mentions and queues them for delivery.

## Methods

### queueOutgoingWebmentions()
`public static function queueOutgoingWebmentions(string $sourceUrl, array $frontmatter, string $content, ?PDO $db = null): void`

Extracts outgoing links and queues them for webmention sending.

@param string $sourceUrl The URL of the post we just created.
@param array<string, mixed> $frontmatter The frontmatter of the post.
@param string $content The Markdown or HTML content of the post.
@param ?PDO $db Optional database connection.
@return void

### discoverEndpoint()
`public static function discoverEndpoint(string $targetUrl): ?string`

Discovers a Webmention endpoint for a target URL according to W3C specification.

@param string $targetUrl The target URL to discover an endpoint for.
@return string|null The discovered endpoint URL, or null if not found.

### discoverEndpointDetails()
`public static function discoverEndpointDetails(string $targetUrl): array`

Discovers Webmention endpoint details including discovery method and response code.

@param string $targetUrl
@return array{target: string, effective_url: string, http_code: int, endpoint: ?string, method: ?string}

### parseHeaderEndpoint()
`public static function parseHeaderEndpoint(string $headers, string $effectiveUrl): ?string`

Parses HTTP Link headers looking for rel="webmention".

@param string $headers Raw HTTP response headers.
@param string $effectiveUrl Base URL for resolving relative links.
@return string|null Resolved endpoint URL, or null if not found.

### parseHtmlEndpoint()
`public static function parseHtmlEndpoint(string $html, string $effectiveUrl): ?array`

Parses HTML body for <link> or <a> tags with rel="webmention" in document order.

@param string $html
@param string $effectiveUrl Base URL for resolving relative links.
@return array{endpoint: string, tag: string}|null

### sendWebmention()
`public static function sendWebmention(string $endpoint, string $source, string $target): array`

Sends a Webmention ping to the discovered endpoint.

@param string $endpoint The Webmention endpoint URL.
@param string $source The source URL of the mentioning post.
@param string $target The target URL being mentioned.
@return array{http_code: int, success: bool, response: string, error: ?string}

### resolveUrl()
`public static function resolveUrl(string $base, string $rel): string`

Resolves a relative URL against a base URL according to RFC 3986.

@param string $base Base URL.
@param string $rel Relative or absolute target URL.
@return string Fully-qualified resolved URL.
