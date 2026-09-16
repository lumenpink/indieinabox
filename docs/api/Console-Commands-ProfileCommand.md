# ProfileCommand
**Namespace:** `Indieinabox\Console\Commands`

Command to manage identity, profile bio, avatar, and banner images via CLI.

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

### resizeImage()
`private function resizeImage(string $src, string $dest, int $maxWidth, int $maxHeight): void`

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
