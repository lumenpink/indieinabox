# ArchiveHandler
**Namespace:** `Indieinabox`

Class ArchiveHandler

Handles viewing of local archived link snapshots (HTML/PDF/Wayback)
and queuing forced snapshot updates.

## Properties

### `private Indieinabox\Site $site`

@var Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

@param Site $site

### handle()
`public function handle(): void`

Handles /archive requests to display local link snapshots or external archive fallbacks.

@return void

### handleForce()
`public function handleForce(): void`

Handles /archive/force POST requests to queue a fresh snapshot.

@return void

### renderArchiveView()
`public function renderArchiveView(string $url, mixed $snapshot): string`

Renders the archive iframe toolbar and viewer HTML markup.

@param string $url
@param array<string, mixed>|false $snapshot
@return string
