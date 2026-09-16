# CommandBus
**Namespace:** `Indieinabox\Commands`

Default CommandBus implementation for CQRS commands.

## Properties

### `private array $handlers`

@var array<string, callable|string>

## Methods

### __construct()
`public function __construct(array $handlers = [])`

@param array<string, callable|string> $handlers

### dispatch()
`public function dispatch(object $command): ?mixed`

Dispatches a command to its registered handler and returns the execution result.

@param object $command
@return mixed
@throws InvalidArgumentException

### register()
`public function register(string $commandClass, callable|string $handler): void`

Registers a handler mapping for a specific command class.

@param class-string $commandClass
@param callable|class-string $handler

### hasHandler()
`public function hasHandler(string $commandClass): bool`

Checks if a command class has a registered handler.

@param class-string $commandClass
