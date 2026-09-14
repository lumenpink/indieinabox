# MarkdownParser
**Namespace:** `Indieinabox`

Class MarkdownParser

Coordinates parsing a Markdown content file into a typed `Page` entity.
Validates supported file extensions, extracts YAML frontmatter, detects locale prefixes,
constructs canonical slugs and relative links, resolves layouts, and maps localized metadata.

## Properties

### `private Indieinabox\Markdown\FileProcessor $fileProcessor`
Validates file extensions and layout mappings.

### `private Indieinabox\Markdown\ContentProcessor $contentProcessor`
Extracts frontmatter and sanitizes inline tags.

### `private Indieinabox\Markdown\LanguageProcessor $languageProcessor`
Processes translations and language paths.

### `private Indieinabox\Site $site`
Site configuration instance.

## Methods

### __construct()
```php
public function __construct(
    FileProcessor $fileProcessor,
    ContentProcessor $contentProcessor,
    LanguageProcessor $languageProcessor,
    Site $site
)
```
Initializes the parser with all required processor dependencies and site settings.

### parse()
```php
public function parse(string $file): ?Indieinabox\Page
```
Parses a markdown source file into a populated `Page` object.
Returns `null` if the file is not supported, if `publish: false`, or if `!buildAll` and missing frontmatter.

### detectLanguage()
```php
public function detectLanguage(string $relPath, array $langs, string $defaultLang): array
```
Extracts the language code from the top-level directory segment if it matches an active language,
returning `[detectedLang, cleanRelPath, isRoot]`.

### buildSlug()
```php
public function buildSlug(
    string $cleanRelPath,
    array $fileInfo,
    array $page,
    string $detectedLang,
    string $defaultLang
): string
```
Builds the canonical slug (with language prefix for non-default languages, slugified path parts,
and trailing slash or `.html` according to the `prettylinks` setting).

### calculateRelativePath()
```php
public function calculateRelativePath(string $slug): string
```
Calculates directory traversal steps (`./`, `../`, `../../`) based on the slug's depth.

### Getters
- `getFileProcessor(): FileProcessor`
- `getContentProcessor(): ContentProcessor`
- `getLanguageProcessor(): LanguageProcessor`
- `getSite(): Site`
