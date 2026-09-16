# DeletePostCommandHandler
**Namespace:** `Indieinabox\Commands\Handlers`

Handler for DeletePostCommand.

## Properties

### `private ?Indieinabox\Repositories\Contracts\ContentRepositoryInterface $contentRepo`

### `private ?Indieinabox\Services\OutboxService $outboxService`

## Methods

### __construct()
`public function __construct(?Indieinabox\Repositories\Contracts\ContentRepositoryInterface $contentRepo = null, ?Indieinabox\Services\OutboxService $outboxService = null)`

### handle()
`public function handle(Indieinabox\Commands\DeletePostCommand $command): array`

@param DeletePostCommand $command
@return array{status: int, headers: array<string, string>, error?: string, error_description?: string}

### resolveFilepathFromUrl()
`private function resolveFilepathFromUrl(Indieinabox\Site\Site $site, string $url, Indieinabox\Repositories\Contracts\ContentRepositoryInterface $contentRepo): ?string`
