<?php

declare(strict_types=1);

namespace Indieinabox\Events;

/**
 * Dispatched when a remote ActivityPub follower activity (Follow or Undo Follow) is processed.
 */
final class FollowActivityReceivedEvent extends DomainEvent
{
    private string $actorUrl;
    private string $inboxUrl;
    private string $activityType;
    private ?string $sharedInboxUrl;

    public function __construct(
        string $actorUrl,
        string $inboxUrl,
        string $activityType = 'Follow',
        ?string $sharedInboxUrl = null
    ) {
        parent::__construct();
        $this->actorUrl = $actorUrl;
        $this->inboxUrl = $inboxUrl;
        $this->activityType = $activityType;
        $this->sharedInboxUrl = $sharedInboxUrl;
    }

    public function getActorUrl(): string
    {
        return $this->actorUrl;
    }

    public function getInboxUrl(): string
    {
        return $this->inboxUrl;
    }

    public function getActivityType(): string
    {
        return $this->activityType;
    }

    public function getSharedInboxUrl(): ?string
    {
        return $this->sharedInboxUrl;
    }
}
