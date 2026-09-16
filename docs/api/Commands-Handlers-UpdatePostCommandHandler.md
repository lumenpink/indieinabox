# UpdatePostCommandHandler
**Namespace:** `Indieinabox\Commands\Handlers`

Handler for UpdatePostCommand.

## Properties

### `private ?Indieinabox\Repositories\Contracts\ContentRepositoryInterface $contentRepo`

## Methods

### __construct()
`public function __construct(?Indieinabox\Repositories\Contracts\ContentRepositoryInterface $contentRepo = null)`

### handle()
`public function handle(Indieinabox\Commands\UpdatePostCommand $command): array`

@param UpdatePostCommand $command
@return array{status: int, headers: array<string, string>, error?: string, error_description?: string}

### resolveFilepathFromUrl()
`private function resolveFilepathFromUrl(Indieinabox\Site\Site $site, string $url, Indieinabox\Repositories\Contracts\ContentRepositoryInterface $contentRepo): ?string`
