# DeletePostCommand
**Namespace:** `Indieinabox\Commands`

Command to delete an existing post and broadcast federation deletion.

## Properties

### `private Indieinabox\Site\Site $site`

### `private string $url`

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, string $url)`

@param Site $site
@param string $url Canonical URL or relative path of the post

### getSite()
`public function getSite(): Indieinabox\Site\Site`

### getUrl()
`public function getUrl(): string`
