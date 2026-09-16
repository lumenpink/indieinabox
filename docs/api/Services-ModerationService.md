# ModerationService
**Namespace:** `Indieinabox\Services`

Service managing moderation workflows for incoming notifications, comments, and interactions.

## Properties

### `private Indieinabox\Repositories\Contracts\InteractionRepositoryInterface $interactions`

## Methods

### __construct()
`public function __construct(?string $dataDir = null, ?Indieinabox\Support\Yaml $yaml = null, ?Indieinabox\Repositories\Contracts\InteractionRepositoryInterface $interactions = null)`

### getRepository()
`public function getRepository(): Indieinabox\Repositories\Contracts\InteractionRepositoryInterface`

### approveInteraction()
`public function approveInteraction(string $id, string $type = 'pending'): bool`

Approves a pending or spam notification by setting status to approved and relocating to notifications dir.

### rejectInteraction()
`public function rejectInteraction(string $id, string $type = 'pending'): bool`

Rejects an interaction by relocating it to the spam directory and updating status to spam.

### deleteInteraction()
`public function deleteInteraction(string $id, string $type = 'pending'): bool`

Deletes an interaction permanently.

### listInteractions()
`public function listInteractions(string $type = 'pending'): array`

Lists interactions by type ('pending', 'approved', or 'spam').

@return array<int, array<string, mixed>>
