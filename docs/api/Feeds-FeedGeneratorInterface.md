# FeedGeneratorInterface
**Namespace:** `Indieinabox\Feeds`

Contract for format-specific feed generators.

## Methods

### generate()
`abstract public function generate(array $entries, string $outputPath, Indieinabox\Site\Site $site, string $lang = 'en'): void`

Generates a feed from a list of Entry objects and writes to the destination path.

@param Entry[] $entries
@param string $outputPath
@param Site $site
@param string $lang

### getFilename()
`abstract public function getFilename(): string`

Returns the default filename for this feed format (e.g. 'rss.xml', 'atom.xml', 'twtxt.txt').
