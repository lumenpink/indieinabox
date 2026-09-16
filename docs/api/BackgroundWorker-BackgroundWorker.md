# BackgroundWorker
**Namespace:** `Indieinabox\BackgroundWorker`

Class BackgroundWorker

Coordinates and dispatches background tasks: inbox queue, outbox delivery,
outgoing webmentions, archive snapshotting, backups, and updates.

## Properties

### `private PDO $db`

@var PDO

### `private Indieinabox\Site $site`

@var Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Initializes the BackgroundWorker.

@param Site $site Global site configuration and environment.

### runAll()
`public function runAll(): void`

Executes all background tasks (inbox, outbox, archives, feeds, updates).
Normally called periodically via cron or CLI.

@return void

### processWebmentionDiscovery()
`public function processWebmentionDiscovery(): void`

Discovers Webmention support for queued domains.

@return void

### processBackups()
`public function processBackups(): void`

Executes the daily backup if cron is enabled.

@return void

### processUpdates()
`public function processUpdates(): void`

Checks for application updates and performs auto-upgrade if enabled.

@return void

### processTwtxtFeeds()
`public function processTwtxtFeeds(): void`

Fetches remote Twtxt timeline and hub mentions asynchronously.
Rebuilds the site to update the static timeline page if new entries are found.

@return void

### processInboxQueue()
`public function processInboxQueue(): void`

Processes the incoming queue (Webmentions, ActivityPub activities, site rebuilds).

@return void

### processOutgoingWebmentions()
`public function processOutgoingWebmentions(): void`

Processes outgoing webmentions.

@return void

### processOutbox()
`public function processOutbox(): void`

Processes the outgoing queue (Outbox).
Delivers queued activities to followers' inboxes using HTTP Signatures.

@return void

### processArchiveQueue()
`public function processArchiveQueue(): void`

Processes the archive queue.
Saves external links to Archive.org and downloads local PDF snapshots via Microlink.

@return void
