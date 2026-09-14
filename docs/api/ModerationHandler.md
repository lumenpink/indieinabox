# ModerationHandler
**Namespace:** `Indieinabox`

Class ModerationHandler

Provides the admin interface and logic for moderating incoming comments,
webmentions, and other interactions (e.g., pending or spam).

## Properties

### `private Indieinabox\Site $site`

@var \Indieinabox\Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Initializes the moderation handler with the site configuration context.

@param \Indieinabox\Site $site The site configuration object.

### handle()
`public function handle(): void`

Main entry point for the moderation panel.
Enforces admin authentication and routes to either action handling (POST)
or rendering the interface (GET).

@return void

### handleAction()
`private function handleAction(): void`

Processes moderation form actions (approve, delete).
Moves or modifies the YAML/Markdown files in the notifications or spam directories.

@return void

### renderInterface()
`private function renderInterface(): void`

Renders the moderation UI.
Scans the notification and spam directories, parses the pending files,
sorts them by date, and outputs the HTML layout for the admin to review.

@return void
