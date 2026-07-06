# WebRouter
**Namespace:** `Indieinabox`

Class WebRouter

## Properties

### `protected Indieinabox\Site $site`

@var \Indieinabox\Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Method __construct
@param \Indieinabox\Site $site

### handleRequest()
`public function handleRequest(): void`

Method handleRequest
@return void

### createWebmentionHandler()
`protected function createWebmentionHandler(): Indieinabox\WebmentionHandler`

Method createWebmentionHandler
@return \Indieinabox\WebmentionHandler

### createIndieAuthHandler()
`protected function createIndieAuthHandler(): Indieinabox\IndieAuthHandler`

Method createIndieAuthHandler
@return \Indieinabox\IndieAuthHandler

### createConfigHandler()
`protected function createConfigHandler(): Indieinabox\ConfigHandler`

Method createConfigHandler
@return \Indieinabox\ConfigHandler

### createMicropubHandler()
`protected function createMicropubHandler(): Indieinabox\MicropubHandler`

Method createMicropubHandler
@return \Indieinabox\MicropubHandler

### createMicropubClientHandler()
`protected function createMicropubClientHandler(): Indieinabox\MicropubClientHandler`

Method createMicropubClientHandler
@return \Indieinabox\MicropubClientHandler

### createMicrosubHandler()
`protected function createMicrosubHandler(): Indieinabox\MicrosubHandler`

Method createMicrosubHandler
@return \Indieinabox\MicrosubHandler

### createMicrosubReaderHandler()
`protected function createMicrosubReaderHandler(): Indieinabox\MicrosubReaderHandler`

Method createMicrosubReaderHandler
@return \Indieinabox\MicrosubReaderHandler

### createActivityPubHandler()
`protected function createActivityPubHandler(): Indieinabox\ActivityPubHandler`

Method createActivityPubHandler
@return \Indieinabox\ActivityPubHandler

### serveStatic()
`private function serveStatic(): void`

Method serveStatic
@return void

### handleArchive()
`private function handleArchive(): void`

Method handleArchive
@return void

### handleArchiveForce()
`private function handleArchiveForce(): void`

Method handleArchiveForce
@return void
