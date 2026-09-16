# UpdatePostCommand
**Namespace:** `Indieinabox\Commands`

Command to update properties of an existing post.

## Properties

### `private Indieinabox\Site\Site $site`

### `private string $url`

### `private array $replace`

/** @var array<string, mixed> */

### `private array $add`

/** @var array<string, mixed> */

### `private array $delete`

/** @var array<int, string> */

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, string $url, array $replace = [], array $add = [], array $delete = [])`

@param Site $site
@param string $url Canonical URL or relative path of the post
@param array<string, mixed> $replace Properties to replace
@param array<string, mixed> $add Properties to append
@param array<int, string> $delete Property keys to remove

### getSite()
`public function getSite(): Indieinabox\Site\Site`

### getUrl()
`public function getUrl(): string`

### getReplace()
`public function getReplace(): array`

@return array<string, mixed>

### getAdd()
`public function getAdd(): array`

@return array<string, mixed>

### getDelete()
`public function getDelete(): array`

@return array<int, string>
