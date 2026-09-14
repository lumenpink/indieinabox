# CliHandler
**Namespace:** `Indieinabox`

## Properties

### `private Indieinabox\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

### getOption()
`private function getOption(array $argv, string $longOpt): ?string`

### handleProfile()
`public function handleProfile(array $argv): void`

### handlePost()
`public function handlePost(array $argv): void`

### resizeImage()
`private function resizeImage(string $src, string $dest, int $maxWidth, int $maxHeight): void`

### handleSetup()
`public function handleSetup(array $argv): void`

### handleConfig()
`public function handleConfig(array $argv): void`

### handleTestWebmention()
`public function handleTestWebmention(array $argv): void`

Handles the 'test-webmention' CLI command to test endpoint discovery and ping delivery.

@param array<int, string> $argv
@return void

### handleValidateHcard()
`private function handleValidateHcard(array $argv): void`

Validates the presence and completeness of an h-card for IndieWebify.me Level 1.

@param array<int, string> $argv
@return void
