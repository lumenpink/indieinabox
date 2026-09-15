<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\ArchiveHandler;
use Indieinabox\Site;

/**
 * Controller handling the web archive viewer and forced snapshot captures.
 */
class ArchiveController extends AbstractController
{
    private ArchiveHandler $handler;

    public function __construct(Site $site, ?ArchiveHandler $handler = null)
    {
        parent::__construct($site);
        $this->handler = $handler ?? new ArchiveHandler($site);
    }

    /**
     * Renders or serves the archive index and stored snapshots.
     */
    public function handle(): void
    {
        $this->handler->handle();
    }

    /**
     * Triggers a forced archive snapshot.
     */
    public function force(): void
    {
        $this->handler->handleForce();
    }
}
