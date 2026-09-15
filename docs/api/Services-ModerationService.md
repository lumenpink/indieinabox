# ModerationService
**Namespace:** `Indieinabox\Services`

Service managing moderation workflows for incoming notifications, comments, and interactions.

## Properties

### `private string $dataDir`

### `private Indieinabox\Yaml $yaml`

## Methods

### __construct()
`public function __construct(?string $dataDir = null, ?Indieinabox\Yaml $yaml = null)`

### approveInteraction()
`public function approveInteraction(string $id, string $type = 'pending'): bool`

Approves a pending or spam notification by setting status to approved and relocating to notifications dir.

### deleteInteraction()
`public function deleteInteraction(string $id, string $type = 'pending'): bool`

Deletes an interaction file permanently.

### listInteractions()
`public function listInteractions(string $type = 'pending'): array`

Lists interactions by type ('pending', 'approved', or 'spam').

@return array<int, array<string, mixed>>
