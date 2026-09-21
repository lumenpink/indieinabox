# ShortlinkService
**Namespace:** `Indieinabox\Services`

Class ShortlinkService

## Properties

### `private string $cacheDir`

@var string

## Methods

### __construct()
`public function __construct(?string $cacheDir = null)`

@param string|null $cacheDir

### getShortlink()
`public function getShortlink(Indieinabox\Page\Page $page, string $fqdn, array $config, bool $isDev = false): string|false`

Gets a shortlink for a page, from cache or by requesting the server.

@param Page $page
@param string $fqdn
@param array $config

@return false|string
