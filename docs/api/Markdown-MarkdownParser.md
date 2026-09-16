# MarkdownParser
**Namespace:** `Indieinabox\Markdown`

Class MarkdownParser

Coordinates parsing a markdown file into a typed Page object:
validates extensions, extracts YAML frontmatter, detects languages,
builds canonical slugs, determines layouts, and maps metadata.

## Properties

### `private Indieinabox\Markdown\FileProcessor $fileProcessor`

@var FileProcessor

### `private Indieinabox\Markdown\ContentProcessor $contentProcessor`

@var ContentProcessor

### `private Indieinabox\Markdown\LanguageProcessor $languageProcessor`

@var LanguageProcessor

### `private Indieinabox\Site $site`

@var Site

## Methods

### __construct()
`public function __construct(Indieinabox\Markdown\FileProcessor $fileProcessor, Indieinabox\Markdown\ContentProcessor $contentProcessor, Indieinabox\Markdown\LanguageProcessor $languageProcessor, Indieinabox\Site $site)`

@param FileProcessor $fileProcessor
@param ContentProcessor $contentProcessor
@param LanguageProcessor $languageProcessor
@param Site $site

### getFileProcessor()
`public function getFileProcessor(): Indieinabox\Markdown\FileProcessor`

@return FileProcessor

### getContentProcessor()
`public function getContentProcessor(): Indieinabox\Markdown\ContentProcessor`

@return ContentProcessor

### getLanguageProcessor()
`public function getLanguageProcessor(): Indieinabox\Markdown\LanguageProcessor`

@return LanguageProcessor

### getSite()
`public function getSite(): Indieinabox\Site`

@return Site

### parse()
`public function parse(string $file): ?Indieinabox\Page`

Parses a markdown file from disk into a populated Page object.

@param string $file The path to the markdown file.
@return Page|null The parsed page or null if invalid or skipped.

### detectLanguage()
`public function detectLanguage(string $relPath, array $langs, string $defaultLang): array`

Detects page language from the top-level directory segment.

@param string $relPath
@param string[] $langs
@param string $defaultLang
@return array{0: string, 1: string, 2: bool} [detectedLang, cleanRelPath, isRoot]

### buildSlug()
`public function buildSlug(string $cleanRelPath, array $fileInfo, array $page, string $detectedLang, string $defaultLang): string`

Builds the canonical slug for a page based on its relative path and settings.

@param string $cleanRelPath
@param array<string, mixed> $fileInfo
@param array<string, mixed> $page
@param string $detectedLang
@param string $defaultLang
@return string

### calculateRelativePath()
`public function calculateRelativePath(string $slug): string`

Calculates the relative traversal path (e.g., './' or '../../') based on slug depth.

@param string $slug
@return string

### setMetadata()
`private function setMetadata(Indieinabox\Page $page, array $rawPage): Indieinabox\Page`

Applies metadata, localized kind mappings, and localized date formatting to the Page object.

@param Page $page
@param array<string, mixed> $rawPage
@return Page
