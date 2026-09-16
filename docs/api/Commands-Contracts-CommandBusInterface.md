# CommandBusInterface
**Namespace:** `Indieinabox\Commands\Contracts`

Interface CommandBusInterface

Defines the contract for registering and dispatching CQRS commands to their respective handlers.

## Methods

### dispatch()
`abstract public function dispatch(object $command): ?mixed`

Dispatches a command to its registered handler and returns the execution result.

@param object $command
@return mixed

### register()
`abstract public function register(string $commandClass, callable|string $handler): void`

Registers a handler mapping for a specific command class.

@param class-string $commandClass
@param callable|class-string $handler

### hasHandler()
`abstract public function hasHandler(string $commandClass): bool`

Checks if a command class has a registered handler.

@param class-string $commandClass
