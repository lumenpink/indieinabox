# Content
**Namespace:** `Indieinabox\Page`

Class Content

This class handles content-related properties of the page.

## Properties

### `public mixed $content`

@var string

### `public mixed $originalcontent`

@var string

### `public mixed $images`

@var array<string>

### `public mixed $rawBody`

@var string|null

## Methods

### __construct()
`public function __construct(string $content = 'Hello World', string $originalcontent = 'Hello World', array $images = [], ?string $rawBody = null)`

PageContent constructor.

@param string $content
@param string $originalcontent
@param array<string> $images
@param string|null $rawBody

### __toString()
`public function __toString(): string`

Convert Content object to its string representation.

@return string
