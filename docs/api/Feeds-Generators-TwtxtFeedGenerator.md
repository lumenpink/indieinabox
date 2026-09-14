# TwtxtFeedGenerator
**Namespace:** `Indieinabox\Feeds\Generators`

Generates a twtxt.txt feed from Entry objects.

## Methods

### getFilename()
`public function getFilename(): string`

### generate()
`public function generate(array $entries, string $outputPath, Indieinabox\Site $site, string $lang = 'en'): void`

@param Entry[] $entries

### prepareEntries()
`private function prepareEntries(array $entries): array`

@param Entry[] $entries
@return Entry[]

### formatEntryToTwtxt()
`private function formatEntryToTwtxt(Indieinabox\Entry\Entry $entry, string $fqdn, Indieinabox\Site $site): string`

### resolveEntryUrl()
`private function resolveEntryUrl(Indieinabox\Entry\Entry $entry, string $fqdn): string`
