# ContentScanner
**Namespace:** `Indieinabox\SiteBuilder`

Scans directories for Markdown content files and manages initial page collection.

## Properties

### `private Indieinabox\Site $site`

### `private Indieinabox\ParserInterface $parser`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\ParserInterface $parser = null)`

### getParser()
`public function getParser(): Indieinabox\ParserInterface`

### scan()
`public function scan(string $dir, Indieinabox\Pages $pages): void`

Recursively scans a directory for markdown content files.
Parses valid markdown files into Page objects and adds them to the collection.
Skips system directories (e.g., app, vendor, output dirs).

@param string $dir The directory path to scan.
@param Pages $pages The collection to populate.
@return void

### ensureMandatoryHomepage()
`public function ensureMandatoryHomepage(Indieinabox\Pages $pages): void`

Ensures a mandatory homepage (index.html) exists in the output.
If one was not provided in the content directory, it creates a generic fallback.

@param Pages $pages
@return void

### renderRawBodies()
`public function renderRawBodies(Indieinabox\Pages $pageCollection): void`

Renders raw markdown bodies into final HTML content for all pages in the collection.
Sets global variables $pages and $site for template and processor compatibility.

@param Pages $pageCollection The collection of pages to render.
@return void
