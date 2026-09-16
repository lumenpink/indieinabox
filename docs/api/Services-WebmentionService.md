# WebmentionService
**Namespace:** `Indieinabox\Services`

Service orchestrating incoming and outgoing Webmentions, verification, and persistence.

## Properties

### `private PDO $db`

### `private Indieinabox\Webmention\SourceVerifier $sourceVerifier`

## Methods

### __construct()
`public function __construct(?PDO $db = null, ?Indieinabox\Webmention\SourceVerifier $sourceVerifier = null)`

### queue()
`public function queue(string $source, string $target): bool`

Enqueues an incoming webmention for asynchronous background processing.

### verifySourceLink()
`public function verifySourceLink(string $source, string $target): array`

Verifies that the source URL contains a valid link back to the target.

@return array{success: bool, message?: string, content?: array{title: string, text: string, whostyle?: array<array-key, mixed>|null}}

### isValidTarget()
`public function isValidTarget(string $target, Indieinabox\Site\Site $site): bool`

Validates whether a target URL belongs to this site and resolves to an existing page file.

### getMentions()
`public function getMentions(string $slug): array`

Retrieves stored webmentions for a given page slug.

@return array<int, array<string, mixed>>

### saveMention()
`public function saveMention(string $slug, array $mentionData): bool`

Saves or appends a webmention entry for a given page slug.

@param array<string, mixed> $mentionData
