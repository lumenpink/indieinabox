# WebRouter
**Namespace:** `Indieinabox`

Class WebRouter

Handles incoming HTTP requests by mapping the request URI to the appropriate
handler class (e.g., Micropub, Microsub, Admin panel, ActivityPub, Webmention, Archive).
If no specific handler matches, it serves static files or emits 404.

## Properties

### `protected Indieinabox\Site $site`

@var Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Initializes the WebRouter with the global site configuration.

@param Site $site The site configuration object.

### handleRequest()
`public function handleRequest(): void`

Main entry point for routing requests.
Parses the current REQUEST_URI, checks against known API/Admin endpoints,
and delegates to the respective handler. Falls back to serveStatic().

@return void

### createWebmentionHandler()
`protected function createWebmentionHandler(): Indieinabox\WebmentionHandler`

Factory method to create a WebmentionHandler instance.

@return WebmentionHandler

### createIndieAuthHandler()
`protected function createIndieAuthHandler(): Indieinabox\IndieAuthHandler`

Factory method to create an IndieAuthHandler instance.

@return IndieAuthHandler

### createConfigHandler()
`protected function createConfigHandler(): Indieinabox\ConfigHandler`

Factory method to create a ConfigHandler instance (Admin panel configuration).

@return ConfigHandler

### createMicropubHandler()
`protected function createMicropubHandler(): Indieinabox\MicropubHandler`

Factory method to create a MicropubHandler instance (Micropub Server).

@return MicropubHandler

### createMicropubClientHandler()
`protected function createMicropubClientHandler(): Indieinabox\MicropubClientHandler`

Factory method to create a MicropubClientHandler instance (Admin panel publishing).

@return MicropubClientHandler

### createMicrosubHandler()
`protected function createMicrosubHandler(): Indieinabox\MicrosubHandler`

Factory method to create a MicrosubHandler instance (Microsub Server).

@return MicrosubHandler

### createMicrosubReaderHandler()
`protected function createMicrosubReaderHandler(): Indieinabox\MicrosubReaderHandler`

Factory method to create a MicrosubReaderHandler instance (Admin panel reader).

@return MicrosubReaderHandler

### createModerationHandler()
`protected function createModerationHandler(): Indieinabox\ModerationHandler`

Factory method to create a ModerationHandler instance (Admin panel moderation).

@return ModerationHandler

### createActivityPubHandler()
`protected function createActivityPubHandler(): Indieinabox\ActivityPubHandler`

Factory method to create an ActivityPubHandler instance (Fediverse integration).

@return ActivityPubHandler

### createArchiveHandler()
`protected function createArchiveHandler(): Indieinabox\ArchiveHandler`

Factory method to create an ArchiveHandler instance.

@return ArchiveHandler

### serveStatic()
`protected function serveStatic(): void`

Attempts to serve static files from the output directory based on the request URI.
Determines MIME types and outputs appropriate headers.
Supports content negotiation for ActivityPub requests.

@return void

### getMimeType()
`public function getMimeType(string $extension): string`

Resolves the MIME content-type for a file extension.

@param string $extension
@return string
