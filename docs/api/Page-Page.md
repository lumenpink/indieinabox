# Page
**Namespace:** `Indieinabox\Page`

Class Page

This class represents a page and composes metadata, content, and localization.

@property string $lang
@property string $langpath
@property array<string>|string $langslug
@property array<string> $otherlang
@property array<string> $otherlangpath
@property string $localizeddate
@property string $localizedkind
@property string $title
@property array<string> $tags
@property array<string> $category
@property string $nick
@property bool $noauthor
@property string $kind
@property string $layout
@property string $originalcontent
@property array<string> $images
@property string $rawBody
@property string $isodate

## Properties

### `public mixed $metadata`

@var Metadata

### `public mixed $content`

@var Content

### `public mixed $localization`

@var Localization

### `public mixed $date`

@var DateTime

### `public mixed $relpath`

@var string

### `public mixed $slug`

@var string

### `public ?string $shortlink`

@var string|null

### `public ?string $filepath`

@var string|null

## Methods

### __construct()
`public function __construct(?Indieinabox\Page\Metadata $metadata = null, ?Indieinabox\Page\Content $content = null, ?Indieinabox\Page\Localization $localization = null, ?DateTime $date = null, string $relpath = '', string $slug = 'untitled')`

Page constructor.

@param Metadata $metadata
@param Content $content
@param Localization $localization
@param DateTime|null $date
@param string $relpath
@param string $slug

### __get()
`public function __get(string $name)`

Magic getter to expose shortcut properties.

@param string $name
@return mixed

### __set()
`public function __set(string $name, mixed $value): void`

Magic setter to modify shortcut properties.

@param string $name
@param mixed $value
@return void

### __isset()
`public function __isset(string $name): bool`

Magic isset check for shortcut properties.

@param string $name
@return bool

### fromArray()
`public static function fromArray(array $data): Indieinabox\Page\Page`

Create a Page object from a raw array structure.

@param array<string, mixed> $data
@return self

### __clone()
`public function __clone()`

Deep clones the Page object to ensure nested Metadata, Content,
and Localization objects are also duplicated.

### toEntry()
`public function toEntry(): Indieinabox\Entry\Entry`

Converts this Page instance to a canonical Entry entity.

@return \Indieinabox\Entry\Entry

### isDraft()
`public function isDraft(): bool`

Checks whether this page is marked as a draft.

### hasTitle()
`public function hasTitle(): bool`

Checks whether this page has a custom title.

### getTitle()
`public function getTitle(): string`

Returns the title of the page with fallback translation.

### getSlug()
`public function getSlug(): string`

Returns the canonical slug of the page.

### getKind()
`public function getKind(): string`

Returns the taxonomy kind of the page.

### getLanguage()
`public function getLanguage(): string`

Returns the language code of the page.

### hasTag()
`public function hasTag(string $tag): bool`

Checks whether the page contains a specific tag.

### getDate()
`public function getDate(): DateTime`

Returns the publication date of the page.

### toArray()
`public function toArray(): array`

Converts this Page instance into an associative array structure.

@return array<string, mixed>
