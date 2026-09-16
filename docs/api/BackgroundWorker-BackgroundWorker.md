# BackgroundWorker
**Namespace:** `Indieinabox\BackgroundWorker`

Class BackgroundWorker

Coordinates and dispatches background tasks: inbox queue, outbox delivery,
outgoing webmentions, archive snapshotting, backups, and updates.

## Properties

### `private PDO $db`

### `private Indieinabox\Site\Site $site`

### `private Indieinabox\Repositories\Contracts\SettingsRepositoryInterface $settingsRepo`

### `private Indieinabox\Services\Contracts\UpdateServiceInterface $updateService`

### `private Indieinabox\Services\Contracts\BackupServiceInterface $backupService`

## Methods

### __construct()
`public function __construct(Indieinabox\Site\Site $site, ?Indieinabox\Repositories\Contracts\SettingsRepositoryInterface $settingsRepo = null, ?PDO $db = null, ?Indieinabox\Services\Contracts\UpdateServiceInterface $updateService = null, ?Indieinabox\Services\Contracts\BackupServiceInterface $backupService = null)`

Initializes the BackgroundWorker with dependency injection.

### runAll()
`public function runAll(): void`

Executes all background tasks (inbox, outbox, archives, feeds, updates).
Normally called periodically via cron or CLI.

### processWebmentionDiscovery()
`public function processWebmentionDiscovery(): void`

Discovers Webmention support for queued domains.

### processBackups()
`public function processBackups(): void`

Executes the daily backup if cron is enabled.

### processUpdates()
`public function processUpdates(): void`

Checks for application updates and performs auto-upgrade if enabled.

### processTwtxtFeeds()
`public function processTwtxtFeeds(): void`

Fetches remote Twtxt timeline and hub mentions asynchronously.
Rebuilds the site to update the static timeline page if new entries are found.

### processInboxQueue()
`public function processInboxQueue(): void`

Processes the incoming queue (Webmentions, ActivityPub activities, site rebuilds).

### processOutgoingWebmentions()
`public function processOutgoingWebmentions(): void`

Processes outgoing webmentions.

### processOutbox()
`public function processOutbox(): void`

Processes the outgoing queue (Outbox).
Delivers queued activities to followers' inboxes using HTTP Signatures.

### processArchiveQueue()
`public function processArchiveQueue(): void`

Processes the archive queue.
Saves external links to Archive.org and downloads local PDF snapshots via Microlink.
