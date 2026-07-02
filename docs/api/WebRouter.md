# WebRouter
**Namespace:** `Indieinabox`

## Properties

### `protected Indieinabox\Site $site`

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

### handleRequest()
`public function handleRequest(): void`

### createWebmentionHandler()
`protected function createWebmentionHandler(): Indieinabox\WebmentionHandler`

### createIndieAuthHandler()
`protected function createIndieAuthHandler(): Indieinabox\IndieAuthHandler`

### createConfigHandler()
`protected function createConfigHandler(): Indieinabox\ConfigHandler`

### createMicropubHandler()
`protected function createMicropubHandler(): Indieinabox\MicropubHandler`

### createMicropubClientHandler()
`protected function createMicropubClientHandler(): Indieinabox\MicropubClientHandler`

### createMicrosubHandler()
`protected function createMicrosubHandler(): Indieinabox\MicrosubHandler`

### createMicrosubReaderHandler()
`protected function createMicrosubReaderHandler(): Indieinabox\MicrosubReaderHandler`

### createActivityPubHandler()
`protected function createActivityPubHandler(): Indieinabox\ActivityPubHandler`

### serveStatic()
`private function serveStatic(): void`

### handleArchive()
`private function handleArchive(): void`

### handleArchiveForce()
`private function handleArchiveForce(): void`
