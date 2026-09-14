# MicrosubReaderHandler
**Namespace:** `Indieinabox`

Class MicrosubReaderHandler

## Properties

### `private Indieinabox\Site $site`

@var \Indieinabox\Site

## Methods

### __construct()
`public function __construct(Indieinabox\Site $site)`

Initializes the MicrosubReaderHandler.

@param \Indieinabox\Site $site Global site configuration and environment.

### handle()
`public function handle(): void`

Handles requests for the Microsub reader interface.
Enforces authentication and routes to specific reader actions or views.

@return void
