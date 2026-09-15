<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\ActivityPubHandler;
use Indieinabox\Site;

/**
 * Controller managing HTTP endpoints for ActivityPub federation, actor discovery, and inbox/outbox.
 */
class ActivityPubController extends AbstractController
{
    private ActivityPubHandler $handler;

    public function __construct(Site $site, ?ActivityPubHandler $handler = null)
    {
        parent::__construct($site);
        $this->handler = $handler ?? new ActivityPubHandler($site);
    }

    public function interact(): void
    {
        $this->handler->handleInteract();
    }

    public function authorizeInteraction(): void
    {
        $this->handler->handleAuthorizeInteraction();
    }

    public function webfinger(): void
    {
        $this->handler->handleWebFinger();
    }

    public function actor(): void
    {
        $this->handler->handleActor();
    }

    public function inbox(): void
    {
        $this->handler->handleInbox();
    }

    public function outbox(): void
    {
        $this->handler->handleOutbox();
    }
}
