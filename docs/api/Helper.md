# Helper
**Namespace:** `Indieinabox`

Class Helper

Provides a comprehensive suite of static utilities to support site generation.
This includes functions for file manipulation, URL resolution, HTTP routing,
image processing, and localization handling.

## Methods

### arrayGet()
`public static function arrayGet(array $array, string $key, mixed $default = null)`

Helper function to get a value from nested array with default

@param  array<string, mixed>  $array
@param  string $key
@param  mixed  $default
@return mixed

### timeAgo()
`public static function timeAgo(int $timestamp): string`

Helper function to get time ago string

@param int $timestamp
@return string

### getKindConfig()
`public static function getKindConfig(string $kind): array`

@return array<string, mixed>

### kind()
`public static function kind(mixed $page, ?Indieinabox\Site $siteInstance = null): array`

Helper function to determine the kind of content

@param  Page|array<string, mixed> $page
@return array{localized: string, kind: string}

### getKindFolder()
`public static function getKindFolder(string $kind, string $lang): string`

Get the localized folder name for a specific kind and language.

@param string $kind
@param string $lang
@return string

### kindLabel()
`public static function kindLabel(string $kind, ?string $lang = null): string`

Return a human-readable, localized display label for a post kind.

Maps internal slugs (article, photo, note, jardim, etc.) to their
translated labels defined in config.yml.

@param  string      $kind Internal kind slug
@param  string|null $lang Target language (defaults to current page lang)
@return string

### kindLink()
`public static function kindLink(Indieinabox\Page $page, string $kind): string`

Return a hyperlinked, human-readable display label for a post kind.
The link points to the kind's index page in the corresponding language.

@param  Page   $page The page context where this link is rendered
@param  string $kind Internal kind slug
@return string

### localizeddate()
`public static function localizeddate(mixed $page): array`

Helper function to format dates

@param  Page|array<string, mixed> $page
@return array{long: string, iso: string}

### unaccent()
`public static function unaccent(string $string): string`

Remove accents from a string

@param  string $string
@return string

### utf8ToAscii()
`public static function utf8ToAscii(string $str, string $unknown = '?'): string`

Convert UTF-8 string to ASCII

@param  string $str
@param  string $unknown
@return string

### decodeUtf8Codepoint()
`private static function decodeUtf8Codepoint(string $c): int`

Decode a multi-byte UTF-8 character sequence into its Unicode codepoint.

@param  string $c  Raw multi-byte character (up to 6 bytes)
@return int        Unicode codepoint value

### loadUtf8Bank()
`private static function loadUtf8Bank(int $bank, array $cache): void`

Lazily load a UTF-8 translation bank from disk into the static cache.

@param  int                      $bank         Bank index (high byte of codepoint)
@param  array<int, array<int, string>> &$cache Reference to the static lookup table
@return void

### slugize()
`public static function slugize(string $str): string`

Slugize a string

@param  string $str
@return string

### sortByDate()
`public static function sortByDate(array $pages): array`

Sorts pages by date descending

@param  array<int, array<string, mixed>|Page> $pages
@return array<int, array<string, mixed>|Page>

### recursiveKsort()
`public static function recursiveKsort(array $array): void`

Recursively sorts an array by keys

@param  array<string, mixed> $array
@return void

### getDirContents()
`public static function getDirContents(string $dir, array $results = []): array`

Get directory contents recursively

@param  string $dir
@param  array<int, string> $results
@return array<int, string>

### getoriginalcontent()
`public static function getoriginalcontent(string $slug, string $lang): string`

Get original content slug translation

@param  string $slug
@param  string $lang
@return string

### beautifyhtml()
`public static function beautifyhtml(string $html): string`

Beautify HTML content

@param  string $html
@return string

### minifyhtml()
`public static function minifyhtml(string $html): string`

Minify HTML content

@param  string $html
@return string

### recursiveRmdir()
`public static function recursiveRmdir(string $dir, bool $keepRootDir = false): bool`

Recursively remove directory

@param  string $dir
@param  bool $keepRootDir
@return bool

### translate()
`public static function translate(string $text, ?string $lang = null): string`

Translation lookup

@param  string $text
@param  string|null $lang
@return string

### translateLowercase()
`public static function translateLowercase(string $text): string`

Translate and make lowercase

@param  string $text
@return string

### translateSlugize()
`public static function translateSlugize(string $text): string`

Translate and slugize

@param  string $text
@return string

### updateTranslations()
`public static function updateTranslations(): void`

Update translations file

@return void

### listposts()
`public static function listposts(): string`

List posts, sorting by date descending, up to 10 posts

@return string

### removegeneric()
`public static function removegeneric(mixed $var): bool`

Remove generic/page items from filter list

@param  mixed $var
@return bool

### createThumbnail()
`public static function createThumbnail(string $caminhoOriginal, string $caminhoDestino, int $tamanhoFocal, array $corBG, array $corFG): bool`

@param array<int, int> $corFG
@param array<int, int> $corBG

### ditherImageToGif()
`public static function ditherImageToGif(string $caminhoOriginal, string $caminhoDestino, int $larguraFocal, array $corBG, array $corFG, bool $aplicarAutomacao = true): bool`

Atkinson adaptive dithering using GD to index 8-bit GIF

@param string $caminhoOriginal
@param string $caminhoDestino
@param int $larguraFocal
@param array $corBG
@param array $corFG
@param bool $aplicarAutomacao
@return bool

### ditherAndCropImageToPng()
`public static function ditherAndCropImageToPng(string $caminhoOriginal, string $caminhoDestino, int $targetWidth, int $targetHeight, array $corBG, array $corFG, bool $aplicarAutomacao = true): bool`

Atkinson adaptive dithering with cropping to exact dimensions, saved as PNG

@param string $caminhoOriginal
@param string $caminhoDestino
@param int $targetWidth
@param int $targetHeight
@param array $corBG
@param array $corFG
@param bool $aplicarAutomacao
@return bool

### generateSocialImages()
`public static function generateSocialImages(string $caminhoOriginal, string $caminhoDestinoBase, array $corBG, array $corFG): array`

Generate social media images (OG, JSON-LD sizes)

@param string $caminhoOriginal
@param string $caminhoDestinoBase
@param array $corBG
@param array $corFG
@return array<string, string>

### getSeoMetadata()
`public static function getSeoMetadata(Indieinabox\Page $page): array`

Helper function to extract and normalize SEO metadata

@param Page $page
@return array<string, string>
