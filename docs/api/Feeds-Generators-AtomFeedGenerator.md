# AtomFeedGenerator
**Namespace:** `Indieinabox\Feeds\Generators`

Generates an Atom 1.0 feed from Entry objects.

## Methods

### getFilename()
`public function getFilename(): string`

### generate()
`public function generate(array $entries, string $outputPath, Indieinabox\Site\Site $site, string $lang = 'en'): void`

@param Entry[] $entries

### prepareEntries()
`private function prepareEntries(array $entries, int $limit): array`

@param Entry[] $entries
@return Entry[]

### resolveEntryUrl()
`private function resolveEntryUrl(Indieinabox\Entry\Entry $entry, string $fqdn): string`

### renderPollFallback()
`private function renderPollFallback(?array $poll): string`

@param array{options?: array<array{title: string, votes?: int}>}|null $poll
