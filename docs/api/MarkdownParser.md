# MarkdownParser
**Namespace:** `Indieinabox`

Class MarkdownParser

## Properties

### `private mixed $fileProcessor`

@var FileProcessor

### `private mixed $contentProcessor`

@var ContentProcessor

### `private mixed $languageProcessor`

@var LanguageProcessor

### `private mixed $site`

@var \Indieinabox\Site

## Methods

### __construct()
`public function __construct(Indieinabox\Markdown\FileProcessor $fileProcessor, Indieinabox\Markdown\ContentProcessor $contentProcessor, Indieinabox\Markdown\LanguageProcessor $languageProcessor, Indieinabox\Site $site)`

@param FileProcessor     $fileProcessor
@param ContentProcessor  $contentProcessor
@param LanguageProcessor $languageProcessor
@param \Indieinabox\Site $site

### parse()
`public function parse(string $file)`

@param  string $file
@return Page|false|null

### setMetadata()
`private function setMetadata(Indieinabox\Page $page, array $rawPage): Indieinabox\Page`

@param  Page $page
@param  array $rawPage
@return Page
