<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Core\Container;
use Indieinabox\Repositories\Contracts\InteractionRepositoryInterface;
use Indieinabox\Repositories\FileInteractionRepository;
use Indieinabox\Support\Yaml;

/**
 * Service managing moderation workflows for incoming notifications, comments, and interactions.
 */
class ModerationService
{
    private InteractionRepositoryInterface $interactions;

    public function __construct(
        ?string $dataDir = null,
        ?Yaml $yaml = null,
        ?InteractionRepositoryInterface $interactions = null
    ) {
        if ($interactions !== null) {
            $this->interactions = $interactions;
        } elseif ($dataDir !== null || $yaml !== null) {
            $this->interactions = new FileInteractionRepository($dataDir, $yaml);
        } elseif (class_exists(Container::class)) {
            $this->interactions = Container::getInstance()->make(InteractionRepositoryInterface::class);
        } else {
            $this->interactions = new FileInteractionRepository();
        }
    }

    public function getRepository(): InteractionRepositoryInterface
    {
        return $this->interactions;
    }

    /**
     * Approves a pending or spam notification by setting status to approved and relocating to notifications dir.
     */
    public function approveInteraction(string $id, string $type = 'pending'): bool
    {
        return $this->interactions->updateStatus($id, 'approved', $type);
    }

    /**
     * Rejects an interaction by relocating it to the spam directory and updating status to spam.
     */
    public function rejectInteraction(string $id, string $type = 'pending'): bool
    {
        return $this->interactions->updateStatus($id, 'spam', $type);
    }

    /**
     * Deletes an interaction permanently.
     */
    public function deleteInteraction(string $id, string $type = 'pending'): bool
    {
        return $this->interactions->delete($id, $type);
    }

    /**
     * Lists interactions by type ('pending', 'approved', or 'spam').
     *
     * @return array<int, array<string, mixed>>
     */
    public function listInteractions(string $type = 'pending'): array
    {
        return $this->interactions->listByStatus($type);
    }
}
