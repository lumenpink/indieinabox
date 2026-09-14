# ArchiveHandler
**Namespace:** `Indieinabox`

Class ArchiveHandler

Handles viewing of local archived link snapshots (HTML/PDF/Wayback)
and queuing forced snapshot updates.

## Properties

### `private Indieinabox\Site $site`
The site configuration instance.

## Methods

### __construct()
```php
public function __construct(Site $site)
```
Initializes the handler with site configuration.

### handle()
```php
public function handle(): void
```
Processes `/archive?url={target}&ts={timestamp}` requests.
Resolves aliases, finds the closest matching snapshot from `archived_links`,
and renders the interactive archive viewer toolbar and iframe.

### handleForce()
```php
public function handleForce(): void
```
Processes POST requests to `/archive/force`.
Enqueues the requested URL into `archive_queue` with `force_archive = 1`
and redirects the browser back to the archive view.

### renderArchiveView()
```php
public function renderArchiveView(string $url, array|false $snapshot): string
```
Generates HTML markup containing the snapshot toolbar (time elapsed, PDF link, Wayback link, Force Update button)
and the embedded snapshot iframe.
