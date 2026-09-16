# EventDispatcherInterface
**Namespace:** `Indieinabox\Events\Contracts`

Interface EventDispatcherInterface

Defines the contract for registering event listeners and dispatching domain events.

## Methods

### dispatch()
`abstract public function dispatch(object $event): object`

Dispatches an event to all registered listeners.

@template T of object
@param T $event
@return T

### listen()
`abstract public function listen(string $eventClass, callable $listener, int $priority = 0): void`

Registers a listener callback for a specific event class name.

@param class-string $eventClass
@param callable $listener
@param int $priority Higher number executes earlier (default: 0)

### hasListeners()
`abstract public function hasListeners(string $eventClass): bool`

Checks if any listeners are registered for an event class.

@param class-string $eventClass

### getListeners()
`abstract public function getListeners(string $eventClass): array`

Returns all registered listeners for an event class.

@param class-string $eventClass
@return array<int, callable>

### clearListeners()
`abstract public function clearListeners(?string $eventClass = null): void`

Clears registered listeners for a specific event class or all events.

@param class-string|null $eventClass
