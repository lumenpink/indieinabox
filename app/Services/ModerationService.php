<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Core\Container;
use Indieinabox\Repositories\Contracts\InteractionRepositoryInterface;
use Indieinabox\Repositories\FileInteractionRepository;
use Indieinabox\Services\Contracts\WebPushServiceInterface;
use Indieinabox\Support\Yaml;

/**
 * Service managing moderation workflows for incoming notifications, comments, and interactions.
 */
class ModerationService
{
    private InteractionRepositoryInterface $interactions;
    private ?WebPushServiceInterface $webPush;

    public function __construct(
        ?string $dataDir = null,
        ?Yaml $yaml = null,
        ?InteractionRepositoryInterface $interactions = null,
        ?WebPushServiceInterface $webPush = null
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

        $container = class_exists(Container::class) ? Container::getInstance() : null;
        $this->webPush = $webPush ?? ($container && $container->has(WebPushServiceInterface::class)
            ? $container->get(WebPushServiceInterface::class)
            : null);
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
        $updated = $this->interactions->updateStatus($id, 'approved', $type);
        if ($updated && $this->webPush !== null) {
            $this->webPush->broadcast(
                'Interaction Approved',
                'A pending interaction has been approved and moved to notifications.',
                '/microsub/reader?channel=notifications',
                'approved-' . $id
            );
        }
        return $updated;
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
