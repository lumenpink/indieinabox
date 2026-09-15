<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\MicrosubHandler;
use Indieinabox\MicrosubReaderHandler;
use Indieinabox\Site;

/**
 * Controller handling Microsub server endpoints (channels, timeline, actions) and the reader UI.
 */
class MicrosubController extends AbstractController
{
    private MicrosubHandler $serverHandler;
    private MicrosubReaderHandler $readerHandler;

    public function __construct(
        Site $site,
        ?MicrosubHandler $serverHandler = null,
        ?MicrosubReaderHandler $readerHandler = null
    ) {
        parent::__construct($site);
        $this->serverHandler = $serverHandler ?? new MicrosubHandler($site);
        $this->readerHandler = $readerHandler ?? new MicrosubReaderHandler($site);
    }

    /**
     * Handles standard Microsub API endpoint requests.
     */
    public function handle(): void
    {
        $this->serverHandler->handle();
    }

    /**
     * Handles the Microsub web reader interface.
     */
    public function reader(): void
    {
        $this->readerHandler->handle();
    }
}
