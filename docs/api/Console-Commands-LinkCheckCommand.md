# LinkCheckCommand
**Namespace:** `Indieinabox\Console\Commands`

Command to scan built static site files and verify internal and external hyperlink validity.

## Properties

### `protected Indieinabox\Site\Site $site`

## Methods

### getName()
`public function getName(): string`

### getDescription()
`public function getDescription(): string`

### getUsage()
`public function getUsage(): string`

### execute()
`public function execute(array $argv): int`

### __construct()
`public function __construct(Indieinabox\Site\Site $site)`

### getAliases()
`public function getAliases(): array`

@return array<int, string>

### getOption()
`protected function getOption(array $argv, string $longOpt): ?string`

Extracts an option value following --<longOpt>.

@param array<int, string> $argv

### hasFlag()
`protected function hasFlag(array $argv, string $flag): bool`

Checks if a flag is present in argv.

@param array<int, string> $argv
