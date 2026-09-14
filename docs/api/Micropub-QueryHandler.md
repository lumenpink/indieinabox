# QueryHandler
**Namespace:** `Indieinabox\Micropub`

Class QueryHandler

Handles Micropub GET queries such as 'q=config', 'q=syndicate-to', and 'q=source'.

## Methods

### handle()
`public static function handle(Indieinabox\Site $site, string $query): array`

Executes a Micropub GET query and returns the response payload.

@param Site $site Global site configuration.
@param string $query The 'q' parameter value.
@return array{status: int, headers: array<string, string>, body?: array<string, mixed>, error?: string, error_description?: string}
