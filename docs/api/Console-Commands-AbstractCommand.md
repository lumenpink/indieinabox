# AbstractCommand
**Namespace:** `Indieinabox\Console\Commands`

Base abstract command providing common CLI argument and option parsing helpers.

## Properties

### `protected Indieinabox\Site\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site)`

### getAliases()
`public function getAliases(): array`

@return array<int, string>

### getUsage()
`public function getUsage(): string`

### getOption()
`protected function getOption(array $argv, string $longOpt): ?string`

Extracts an option value following --<longOpt>.

@param array<int, string> $argv

### hasFlag()
`protected function hasFlag(array $argv, string $flag): bool`

Checks if a flag is present in argv.

@param array<int, string> $argv

### getName()
`abstract public function getName(): string`

Returns the primary name of the command.

### getDescription()
`abstract public function getDescription(): string`

Returns a concise description of the command purpose.

### execute()
`abstract public function execute(array $argv): int`

Executes the command using the given CLI arguments.

@param array<int, string> $argv
@return int Exit code (0 for success, non-zero for error)
