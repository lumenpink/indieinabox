# TestWebmentionCommand
**Namespace:** `Indieinabox\Console\Commands`

Command to test Webmention endpoint discovery, outbound ping delivery, and h-card compliance.

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

### validateHcard()
`private function validateHcard(array $argv): void`

Validates the presence and completeness of an h-card for IndieWebify.me Level 1.

@param array<int, string> $argv

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
