# DomainEvent
**Namespace:** `Indieinabox\Events`

Base abstract class for all domain events.

## Properties

### `private DateTimeImmutable $occurredOn`

### `private string $eventId`

### `private bool $propagationStopped`

## Methods

### __construct()
`public function __construct()`

### occurredOn()
`public function occurredOn(): DateTimeImmutable`

### eventId()
`public function eventId(): string`

### isPropagationStopped()
`public function isPropagationStopped(): bool`

### stopPropagation()
`public function stopPropagation(): void`
