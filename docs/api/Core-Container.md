# Container
**Namespace:** `Indieinabox\Core`

PSR-11 compliant Dependency Injection Container with singleton binding,
factory resolution, and constructor autowiring.

## Properties

### `private static ?Indieinabox\Core\Container $instance`

### `private array $instances`

@var array<string, mixed>

### `private array $bindings`

@var array<string, Closure|string>

### `private array $singletons`

@var array<string, bool>

## Methods

### getInstance()
`public static function getInstance(): Indieinabox\Core\Container`

Returns the singleton instance of the container.

### setInstance()
`public static function setInstance(?Indieinabox\Core\Container $container): void`

Sets or resets the global container instance.

### instance()
`public function instance(string $id, ?mixed $instance): void`

Registers an existing object instance into the container.

### bind()
`public function bind(string $id, Closure|string $concrete, bool $singleton = false): void`

Registers a binding with the container.

### singleton()
`public function singleton(string $id, Closure|string $concrete): void`

Registers a shared singleton binding with the container.

### get()
`public function get(string $id): ?mixed`

Finds an entry of the container by its identifier and returns it.

@param string $id Identifier of the entry to look for.
@return mixed Entry.
@throws NotFoundException No entry was found for this identifier.
@throws ContainerException Error while retrieving the entry.

### has()
`public function has(string $id): bool`

Returns true if the container can return an entry for the given identifier.

@param string $id Identifier of the entry to look for.

### make()
`public function make(string $abstract, array $parameters = []): object`

Resolves a class with constructor dependency autowiring.

@template T of object
@param class-string<T> $abstract
@param array<string, mixed> $parameters
@return T
@throws ContainerException

### flush()
`public function flush(): void`

Flushes all stored instances and bindings.
