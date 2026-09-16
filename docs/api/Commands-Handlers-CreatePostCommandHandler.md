# CreatePostCommandHandler
**Namespace:** `Indieinabox\Commands\Handlers`

Handler for CreatePostCommand.

## Properties

### `private ?Indieinabox\Repositories\Contracts\ContentRepositoryInterface $contentRepo`

### `private ?Indieinabox\Events\Contracts\EventDispatcherInterface $eventDispatcher`

### `private ?Indieinabox\Services\OutboxService $outboxService`

## Methods

### __construct()
`public function __construct(?Indieinabox\Repositories\Contracts\ContentRepositoryInterface $contentRepo = null, ?Indieinabox\Events\Contracts\EventDispatcherInterface $eventDispatcher = null, ?Indieinabox\Services\OutboxService $outboxService = null)`

### handle()
`public function handle(Indieinabox\Commands\CreatePostCommand $command): array`

@param CreatePostCommand $command
@return array{status: int, headers: array<string, string>, post_url: string, file_path: string, kind: string, slug: string}
