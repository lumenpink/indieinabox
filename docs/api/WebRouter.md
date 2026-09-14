# WebRouter
**Namespace:** `Indieinabox`

Class WebRouter

Handles incoming HTTP requests by mapping the request URI to the appropriate handler class:
Webmentions, IndieAuth/OAuth, Micropub, Microsub, ActivityPub, BackgroundWorker (cron), and Archive snapshots.
Falls back to serving static files from the public HTML directory or returns 404.

## Properties

### `protected Indieinabox\Site $site`
The site configuration instance.

## Methods

### __construct()
```php
public function __construct(Site $site)
```
Initializes the router with the site configuration.

### handleRequest()
```php
public function handleRequest(): void
```
Main routing dispatcher. Inspects `REQUEST_URI` and HTTP parameters, and executes the matched handler:
- `/webmention`, `/webmentions`, `?webmention` ➔ `createWebmentionHandler()->handle()`
- `/auth`, `/token`, `/.well-known/oauth-authorization-server` ➔ `createIndieAuthHandler()->handle()`
- `/.well-known/micropub` ➔ Redirects to `/micropub`
- `/micropub/client` ➔ `createMicropubClientHandler()->handle()`
- `/micropub` ➔ `createMicropubHandler()->handle()`
- `/microsub/reader` ➔ `createMicrosubReaderHandler()->handle()`
- `/microsub` ➔ `createMicrosubHandler()->handle()`
- ActivityPub routes (`/interact`, `/authorize_interaction`, `/.well-known/webfinger`, `/actor`, `/inbox`, `/outbox`) ➔ `createActivityPubHandler()`
- `/cron` ➔ Runs `BackgroundWorker::runAll()`
- `/archive` ➔ `createArchiveHandler()->handle()`
- `/archive/force` (POST) ➔ `createArchiveHandler()->handleForce()`
- Admin routes (`/admin/config`, `/admin/micropub`, `/admin/microsub`, `/admin/moderation`)
- Static fallback ➔ `serveStatic()`

### serveStatic()
```php
protected function serveStatic(): void
```
Serves static files (HTML, CSS, JS, images, XML, JSON, Gemini). Supports Content Negotiation:
when `HTTP_ACCEPT` requests `application/activity+json` or `application/ld+json`, serves companion `.json` ActivityPub files if available.

### getMimeType()
```php
public function getMimeType(string $extension): string
```
Resolves the MIME content-type string for a given file extension.

### Factory Methods
Overridable factory methods allowing customized or mock handler injection in tests:
- `createWebmentionHandler(): WebmentionHandler`
- `createIndieAuthHandler(): IndieAuthHandler`
- `createConfigHandler(): ConfigHandler`
- `createMicropubHandler(): MicropubHandler`
- `createMicropubClientHandler(): MicropubClientHandler`
- `createMicrosubHandler(): MicrosubHandler`
- `createMicrosubReaderHandler(): MicrosubReaderHandler`
- `createModerationHandler(): ModerationHandler`
- `createActivityPubHandler(): ActivityPubHandler`
- `createArchiveHandler(): ArchiveHandler`
