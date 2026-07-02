# FeedManager
**Namespace:** `Indieinabox\Feeds`

Class FeedManager

## Methods

### preparePages()
`private function preparePages(array $pages, int $limit): array`

Filter and prepare pages for feed generation.

@param Page[] $pages
@param int $limit
@return Page[]

### generateRss()
`public function generateRss(array $pages, string $outputFile, string $fqdn, Indieinabox\Site\Metadata $metadata, int $limit = 20): void`

Generates an RSS 2.0 feed.

@param Page[] $pages
@param string $outputFile
@param string $fqdn
@param Metadata $metadata
@param int $limit

### generateAtom()
`public function generateAtom(array $pages, string $outputFile, string $fqdn, Indieinabox\Site\Metadata $metadata, int $limit = 20): void`

Generates an Atom 1.0 feed.

@param Page[] $pages
@param string $outputFile
@param string $fqdn
@param Metadata $metadata
@param int $limit
