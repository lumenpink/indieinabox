# LinkCheckerService
**Namespace:** `Indieinabox\Services`

## Properties

### `private Indieinabox\Site $site`

### `private array $errors`

### `private array $results`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

### run()
`public function run(?string $reportPath = null, bool $skipExternal = false): void`

### writeReport()
`private function writeReport(string $path, string $checkedAt, int $internalCount, int $externalCount): void`

### getHtmlFiles()
`private function getHtmlFiles(string $dir): array`

### extractLinks()
`private function extractLinks(string $html): array`

### checkInternalLinks()
`private function checkInternalLinks(array $links, string $base, string $htmlDir): void`

### checkExternalLinks()
`private function checkExternalLinks(array $links): void`
