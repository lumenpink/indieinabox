# Options
**Namespace:** `Indieinabox\Site`

Class Options

Holds boolean flags and options for the site.

## Properties

### `public bool $buildAll`

@var bool

### `public bool $dev`

@var bool

### `public bool $skipStatic`

@var bool

### `public bool $forceStaticOverride`

@var bool

### `public ?string $htmlpostprocessing`

@var ?string

### `public bool $prettylinks`

@var bool

### `public int $feed_limit`

@var int

### `public string $translation_parity`

@var string

### `public string $translation_auto`

@var string

## Methods

### __construct()
`public function __construct(bool $buildAll = true, bool $dev = false, bool $skipStatic = false, bool $forceStaticOverride = false, ?string $htmlpostprocessing = null, bool $prettylinks = true, int $feed_limit = 20, string $translation_parity = 'full', string $translation_auto = 'pseudo')`

SiteOptions constructor.

@param bool $buildAll
@param bool $dev
@param bool $skipStatic
@param bool $forceStaticOverride
@param string|null $htmlpostprocessing
@param bool $prettylinks
@param int $feed_limit
@param string $translation_parity
@param string $translation_auto
