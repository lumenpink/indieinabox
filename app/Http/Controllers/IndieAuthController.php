<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\IndieAuthHandler;
use Indieinabox\Site;

/**
 * Controller handling IndieAuth authentication, authorization code exchange, token issues, and metadata.
 */
class IndieAuthController extends AbstractController
{
    private IndieAuthHandler $handler;

    public function __construct(Site $site, ?IndieAuthHandler $handler = null)
    {
        parent::__construct($site);
        $this->handler = $handler ?? new IndieAuthHandler($site);
    }

    /**
     * Dispatches IndieAuth request.
     */
    public function handle(): void
    {
        $this->handler->handle();
    }
}
