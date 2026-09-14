# ExtendedEntry
**Namespace:** `Indieinabox\Microsub`

Universal Post Object (Extended JF2 Entry)

This class represents a unified structure for all posts retrieved
by the Microsub server, regardless of their origin (ActivityPub, Twtxt, RSS).
It safely encapsulates federated extensions into the `_indieinabox` namespace.

## Properties

### `public string $type`

### `public string $uid`

### `public string $url`

### `public string $published`

### `public ?array $author`

### `public array $content`

### `public array $category`

### `public string $network`

### `public string $originServer`

### `public array $capabilities`

### `public ?string $contentWarning`

### `public ?array $poll`

### `public ?array $reels`

### `public bool $isRead`

## Methods

### toJF2Array()
`public function toJF2Array(): array`

Serializes the object into a fully compliant JF2 array,
including the graceful degradation fallbacks.

@return array
