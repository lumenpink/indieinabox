# MicropubClientHandler
**Namespace:** `Indieinabox`

Class MicropubClientHandler

## Properties

### `private Indieinabox\Site $site`

@var \Indieinabox\Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Initializes the MicropubClientHandler.

@param \Indieinabox\Site $site Global site configuration and environment.

### handle()
`public function handle(): void`

Handles POST requests for the Micropub client interface.
Processes form submissions to create new posts or upload media via Micropub.

@return void
