# BackgroundWorkerInterface
**Namespace:** `Indieinabox\BackgroundWorker\Contracts`

Interface BackgroundWorkerInterface

Coordinates and dispatches background processing pipelines:
inbox queue, outbox delivery, outgoing webmentions, archive snapshots,
backups, and application updates.

## Methods

### runAll()
`abstract public function runAll(): void`

Executes all background tasks sequentially under cron lock.

### processInboxQueue()
`abstract public function processInboxQueue(): void`

Processes incoming queue (Webmentions, ActivityPub activities, site rebuilds).

### processOutbox()
`abstract public function processOutbox(): void`

Processes outgoing queue (ActivityPub outbox delivery).

### processOutgoingWebmentions()
`abstract public function processOutgoingWebmentions(): void`

Processes outgoing webmentions delivery.

### processArchiveQueue()
`abstract public function processArchiveQueue(): void`

Processes archive queue (Wayback Machine and snapshots).

### processTwtxtFeeds()
`abstract public function processTwtxtFeeds(): void`

Fetches remote Twtxt timeline and hub mentions asynchronously.

### processBackups()
`abstract public function processBackups(): void`

Executes scheduled daily backups if enabled.

### processWebmentionDiscovery()
`abstract public function processWebmentionDiscovery(): void`

Discovers Webmention endpoints for queued domains.

### processUpdates()
`abstract public function processUpdates(): void`

Checks for application updates and performs auto-upgrade if enabled.
