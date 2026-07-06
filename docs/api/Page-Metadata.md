# Metadata
**Namespace:** `Indieinabox\Page`

Class Metadata

This class handles metadata related to the page.

## Properties

### `public mixed $category`

@var array<string>

### `public mixed $tags`

@var array<string>

### `public mixed $title`

@var string

### `public mixed $nick`

@var string

### `public mixed $noauthor`

@var bool

### `public mixed $kind`

@var string

### `public mixed $layout`

@var string

### `public mixed $maturity`

@var string|null

### `public mixed $reliability`

@var string|null

### `public mixed $menu`

@var string|bool|null

### `public mixed $hide_title`

@var bool

### `public mixed $hide_on_rss`

@var bool|string

### `public mixed $menu_order`

@var int|null

### `public mixed $translated_by_ia`

@var bool|string|null

### `public mixed $description`

@var string|null

### `public mixed $image`

@var string|null

### `public mixed $image_alt`

@var string|null

## Methods

### __construct()
`public function __construct(array $category = [], array $tags = [], string $title = 'Untitled', string $nick = 'untitled', bool $noauthor = false, string $kind = 'note', string $layout = 'page', ?string $maturity = null, ?string $reliability = null, mixed $menu = null, ?int $menu_order = null, bool $hide_title = false, mixed $hide_on_rss = false, mixed $translated_by_ia = null, ?string $description = null, ?string $image = null, ?string $image_alt = null)`

PageMetadata constructor.

@param array<string> $category
@param array<string> $tags
@param string $title
@param string $nick
@param bool $noauthor
@param string $kind
@param string $layout
@param string|null $maturity
@param string|null $reliability
@param bool|null $menu
@param int|null $menu_order
