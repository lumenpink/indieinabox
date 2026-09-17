# IngestInteractionServiceInterface
**Namespace:** `Indieinabox\Services\Contracts`

Interface IngestInteractionServiceInterface

Defines operations for ingesting, validating, and persisting incoming social interactions.

## Methods

### ingest()
`abstract public function ingest(Indieinabox\DTO\InteractionDto $interaction): bool`

Ingests a normalized interaction into the repository and dispatches associated domain events.

@param InteractionDto $interaction
@return bool

### ingestWebmention()
`abstract public function ingestWebmention(string $source, string $target, array $verifiedContent, string $status = 'pending'): Indieinabox\DTO\InteractionDto`

Ingests a verified Webmention payload.

@param string $source
@param string $target
@param array<string, mixed> $verifiedContent
@param string $status
@return InteractionDto

### ingestActivity()
`abstract public function ingestActivity(array $activity, ?array $actorData = null, string $status = 'pending'): ?Indieinabox\DTO\InteractionDto`

Ingests a federated ActivityPub activity.

@param array<string, mixed> $activity
@param array<string, mixed>|null $actorData
@param string $status
@return InteractionDto|null
