# FileProcessor
**Namespace:** `Indieinabox\Markdown`

## Properties

### `private mixed $site`

@var \Indieinabox\Site

### `private mixed $base`

@var string

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, string $base)`

@param \Indieinabox\Site $site
@param string $base

### isValidFile()
`public function isValidFile(string $file): bool`

@param  string $file
@return bool

### getFileInfo()
`public function getFileInfo(string $file): array`

@param  string $file
@return array{ext: string, filename: string}

### generateBaseSlug()
`public function generateBaseSlug(string $file): string`

@param  string $file
@return string

### determineLayout()
`public function determineLayout(array $page): string`

@param  array<string, mixed> $page
@return string
