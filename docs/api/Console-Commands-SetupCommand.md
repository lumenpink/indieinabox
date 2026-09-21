# SetupCommand
**Namespace:** `Indieinabox\Console\Commands`

Command to initialize administrative credentials, domain FQDN, and initial blog identity.

## Properties

### `private Indieinabox\Services\Contracts\InstallServiceInterface $installService`

### `protected Indieinabox\Site\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, ?Indieinabox\Services\Contracts\InstallServiceInterface $installService = null)`

### getName()
`public function getName(): string`

### getDescription()
`public function getDescription(): string`

### getUsage()
`public function getUsage(): string`

### execute()
`public function execute(array $argv): int`

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
