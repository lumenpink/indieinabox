# IngestInteractionService
**Namespace:** `Indieinabox\Services`

Service orchestrating the ingestion, normalization, and persistence of social interactions.

## Properties

### `private Indieinabox\Repositories\Contracts\InteractionRepositoryInterface $interactionRepo`

### `private ?Indieinabox\Events\Contracts\EventDispatcherInterface $eventDispatcher`

## Methods

### __construct()
`public function __construct(?Indieinabox\Repositories\Contracts\InteractionRepositoryInterface $interactionRepo = null, ?Indieinabox\Events\Contracts\EventDispatcherInterface $eventDispatcher = null)`

### ingest()
`public function ingest(Indieinabox\DTO\InteractionDto $interaction): bool`

Ingests a normalized interaction into the repository and dispatches associated domain events.

@param InteractionDto $interaction
@return bool

### ingestWebmention()
`public function ingestWebmention(string $source, string $target, array $verifiedContent, string $status = 'pending'): Indieinabox\DTO\InteractionDto`

Ingests a verified Webmention payload.

@param string $source
@param string $target
@param array<string, mixed> $verifiedContent
@param string $status
@return InteractionDto

### ingestActivity()
`public function ingestActivity(array $activity, ?array $actorData = null, string $status = 'pending'): ?Indieinabox\DTO\InteractionDto`

Ingests a federated ActivityPub activity.

@param array<string, mixed> $activity
@param array<string, mixed>|null $actorData
@param string $status
@return InteractionDto|null
