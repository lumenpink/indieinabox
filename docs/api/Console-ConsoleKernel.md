# ConsoleKernel
**Namespace:** `Indieinabox\Console`

Kernel responsible for registering, routing, and executing CLI commands.

## Properties

### `private Indieinabox\Site\Site $site`

### `private array $commands`

@var array<string, CommandInterface>

### `private array $uniqueCommands`

@var array<string, CommandInterface>

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site)`

### registerDefaultCommands()
`private function registerDefaultCommands(): void`

Registers all standard CLI commands.

### register()
`public function register(Indieinabox\Console\Contracts\CommandInterface $command): void`

Registers a command and its aliases in the registry.

### getCommand()
`public function getCommand(string $name): ?Indieinabox\Console\Contracts\CommandInterface`

Finds a command by name or alias.

### getCommands()
`public function getCommands(): array`

Returns all unique registered commands.

@return array<string, CommandInterface>

### handle()
`public function handle(array $argv): int`

Dispatches the input CLI arguments to the matching command.

@param array<int, string> $argv
@return int Exit code

### printHelp()
`public function printHelp(): void`

Renders a human-readable list of available commands and usage instructions.
