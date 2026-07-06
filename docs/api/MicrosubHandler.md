# MicrosubHandler
**Namespace:** `Indieinabox`

Class MicrosubHandler

## Properties

### `private Indieinabox\IndieAuthHandler $authHandler`

@var \Indieinabox\IndieAuthHandler

### `private PDO $db`

@var PDO

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Method __construct
@param \Indieinabox\Site $site

### handleRequest()
`public function handleRequest(): void`

Method handleRequest
@return void

### handleGet()
`private function handleGet(string $action): void`

Method handleGet
@param string $action

@return void

### handlePost()
`private function handlePost(string $action): void`

Method handlePost
@param string $action

@return void
