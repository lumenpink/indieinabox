# ContentScanner
**Namespace:** `Indieinabox\SiteBuilder`

Class ContentScanner

Scans directories for Markdown content files, initializes the Markdown parser and its processors,
ensures mandatory homepages exist, and renders raw Markdown page bodies into final HTML.

## Methods

### __construct()
```php
public function __construct(Site $site, ?ParserInterface $parser = null)
```
Initializes the scanner with site settings and an optional parser. If no parser is provided,
it initializes `MarkdownParser` with `FileProcessor`, `ContentProcessor`, and `LanguageProcessor`.

### getParser()
```php
public function getParser(): ParserInterface
```
Returns the active parser implementation.

### scan()
```php
public function scan(string $dir, Pages $pages): void
```
Recursively scans a directory for markdown content files, parses valid files into `Page` objects,
and adds them to the collection. Ignores system and output directories.

### ensureMandatoryHomepage()
```php
public function ensureMandatoryHomepage(Pages $pages): void
```
Ensures a mandatory homepage (`/` or `lang/`) exists for all configured languages.
If absent, creates a fallback page with `layout = 'home'`.

### renderRawBodies()
```php
public function renderRawBodies(Pages $pageCollection): void
```
Executes Pass 2 of rendering: transforms raw markdown bodies (`rawBody`) into final HTML content
using `ContentProcessor`, setting global `$pages` and `$site` variables for template and helper compatibility.
