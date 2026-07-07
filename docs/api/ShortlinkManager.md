# ShortlinkManager
**Namespace:** `Indieinabox`

Class ShortlinkManager

## Properties

### `private string $cacheDir`

@var string

## Methods

### __construct()
`public function __construct(?string $cacheDir = null)`

@param string|null $cacheDir

### getShortlink()
`public function getShortlink(Indieinabox\Page $page, string $fqdn, array $config): ?string`

Gets a shortlink for a page, from cache or by requesting the server.

@param Page $page
@param string $fqdn
@param array $config
@return string|null

### generateBoundary()
`private static function generateBoundary(int $length = 24): string`
