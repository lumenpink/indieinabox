# EventDispatcher
**Namespace:** `Indieinabox\Events`

Standard in-memory Event Dispatcher implementation.

## Properties

### `private array $listeners`

@var array<class-string, array<int, array<int, callable>>>

### `private array $sortedListeners`

@var array<class-string, array<int, callable>>

## Methods

### dispatch()
`public function dispatch(object $event): object`

### listen()
`public function listen(string $eventClass, callable $listener, int $priority = 0): void`

### hasListeners()
`public function hasListeners(string $eventClass): bool`

### getListeners()
`public function getListeners(string $eventClass): array`

### clearListeners()
`public function clearListeners(?string $eventClass = null): void`

### resolveListenersForEvent()
`private function resolveListenersForEvent(string $eventClass): array`

Resolves all matching listeners for an event, including parent classes and interfaces.

@param class-string $eventClass
@return array<int, callable>
