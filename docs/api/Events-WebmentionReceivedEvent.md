# WebmentionReceivedEvent
**Namespace:** `Indieinabox\Events`

Dispatched when a verified incoming Webmention is recorded.

## Properties

### `private string $source`

### `private string $target`

### `private string $type`

### `private ?array $author`

/** @var array<string, mixed>|null */

### `private ?string $summary`

## Methods

### __construct()
`public function __construct(string $source, string $target, string $type = 'mention', ?array $author = null, ?string $summary = null)`

@param string $source
@param string $target
@param string $type e.g. 'mention', 'like', 'reply', 'repost'
@param array<string, mixed>|null $author
@param string|null $summary

### getSource()
`public function getSource(): string`

### getTarget()
`public function getTarget(): string`

### getType()
`public function getType(): string`

### getAuthor()
`public function getAuthor(): ?array`

@return array<string, mixed>|null

### getSummary()
`public function getSummary(): ?string`

### occurredOn()
`public function occurredOn(): DateTimeImmutable`

### eventId()
`public function eventId(): string`

### isPropagationStopped()
`public function isPropagationStopped(): bool`

### stopPropagation()
`public function stopPropagation(): void`
