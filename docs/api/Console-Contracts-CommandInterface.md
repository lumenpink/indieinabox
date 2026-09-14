# CommandInterface
**Namespace:** `Indieinabox\Console\Contracts`

Interface defining a standardized CLI command.

## Methods

### getName()
`abstract public function getName(): string`

Returns the primary name of the command.

### getAliases()
`abstract public function getAliases(): array`

Returns alternative aliases that can trigger this command.

@return array<int, string>

### getDescription()
`abstract public function getDescription(): string`

Returns a concise description of the command purpose.

### getUsage()
`abstract public function getUsage(): string`

Returns usage syntax and help instructions.

### execute()
`abstract public function execute(array $argv): int`

Executes the command using the given CLI arguments.

@param array<int, string> $argv
@return int Exit code (0 for success, non-zero for error)
