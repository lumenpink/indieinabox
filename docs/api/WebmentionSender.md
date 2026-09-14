# WebmentionSender
**Namespace:** `Indieinabox`

## Methods

### queueOutgoingWebmentions()
`public static function queueOutgoingWebmentions(string $sourceUrl, array $frontmatter, string $content): void`

Extracts outgoing links and queues them for webmention sending.

@param string $sourceUrl The URL of the post we just created.
@param array<string, mixed> $frontmatter The frontmatter of the post.
@param string $content The Markdown or HTML content of the post.
