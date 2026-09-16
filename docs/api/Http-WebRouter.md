# WebRouter
**Namespace:** `Indieinabox\Http`

Class WebRouter

Handles incoming HTTP requests by mapping the request URI to the appropriate
controller (e.g., Micropub, Microsub, Admin panel, ActivityPub, Webmention, Archive).
If no specific controller matches, it delegates to StaticFileServer.

## Properties

### `protected Indieinabox\Site $site`

@var Site

### `protected Indieinabox\Http\StaticFileServer $fileServer`

@var StaticFileServer

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site, ?Indieinabox\Http\StaticFileServer $fileServer = null)`

Initializes the WebRouter with the global site configuration and static file server.

@param Site $site The site configuration object.
@param StaticFileServer|null $fileServer The static file server instance.

### getFileServer()
`public function getFileServer(): Indieinabox\Http\StaticFileServer`

### handleRequest()
`public function handleRequest(): void`

Main entry point for routing requests.
Parses the current REQUEST_URI, checks against known API/Admin endpoints,
and delegates to the respective handler. Falls back to serveStatic().

@return void

### getWebmentionController()
`public function getWebmentionController(): Indieinabox\Http\Controllers\WebmentionController`

### getIndieAuthController()
`public function getIndieAuthController(): Indieinabox\Http\Controllers\IndieAuthController`

### getMicropubController()
`public function getMicropubController(): Indieinabox\Http\Controllers\MicropubController`

### getMicrosubController()
`public function getMicrosubController(): Indieinabox\Http\Controllers\MicrosubController`

### getActivityPubController()
`public function getActivityPubController(): Indieinabox\Http\Controllers\ActivityPubController`

### getArchiveController()
`public function getArchiveController(): Indieinabox\Http\Controllers\ArchiveController`

### getAdminController()
`public function getAdminController(): Indieinabox\Http\Controllers\AdminController`

### serveStatic()
`protected function serveStatic(): void`

Attempts to serve static files from the output directory via StaticFileServer.

### getMimeType()
`public function getMimeType(string $extension): string`

Resolves the MIME content-type for a file extension via StaticFileServer.

@param string $extension
@return string
