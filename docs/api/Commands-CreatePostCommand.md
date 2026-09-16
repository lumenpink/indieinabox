# CreatePostCommand
**Namespace:** `Indieinabox\Commands`

Command to create and publish a new post.

## Properties

### `private Indieinabox\Site\Site $site`

### `private array $input`

/** @var array<string, mixed> */

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, array $input)`

@param Site $site
@param array<string, mixed> $input Form or JSON Micropub payload

### getSite()
`public function getSite(): Indieinabox\Site\Site`

### getInput()
`public function getInput(): array`

@return array<string, mixed>
