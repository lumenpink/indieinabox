# PostPublishedEvent
**Namespace:** `Indieinabox\Events`

Dispatched when a post (note, article, bookmark, etc.) is successfully authored and published.

## Properties

### `private string $slug`

### `private string $filepath`

### `private string $kind`

### `private ?string $title`

### `private string $content`

### `private array $mediaPaths`

/** @var array<int, string> */

## Methods

### __construct()
`public function __construct(string $slug, string $filepath, string $kind, ?string $title, string $content, array $mediaPaths = [])`

@param string $slug
@param string $filepath
@param string $kind
@param string|null $title
@param string $content
@param array<int, string> $mediaPaths

### getSlug()
`public function getSlug(): string`

### getFilepath()
`public function getFilepath(): string`

### getKind()
`public function getKind(): string`

### getTitle()
`public function getTitle(): ?string`

### getContent()
`public function getContent(): string`

### getMediaPaths()
`public function getMediaPaths(): array`

@return array<int, string>

### occurredOn()
`public function occurredOn(): DateTimeImmutable`

### eventId()
`public function eventId(): string`

### isPropagationStopped()
`public function isPropagationStopped(): bool`

### stopPropagation()
`public function stopPropagation(): void`
