# Page
**Namespace:** `Indieinabox`

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

### `public ?string $filepath`

@var string|null

## Methods

### __construct()
`public function __construct(?Indieinabox\Page\Metadata $metadata, ?Indieinabox\Page\Content $content, ?Indieinabox\Page\Localization $localization, ?DateTime $date = null, string $relpath = '', string $slug = 'untitled')`

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
`public static function fromArray(array $data): self`

Create a Page object from a raw array structure.

@param array<string, mixed> $data
@return self

### __clone()
`public function __clone()`
