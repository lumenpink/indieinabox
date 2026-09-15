<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\MicropubClientHandler;
use Indieinabox\MicropubHandler;
use Indieinabox\Site;

/**
 * Controller handling Micropub server queries, post creation, media uploads, and the web-based posting client.
 */
class MicropubController extends AbstractController
{
    private MicropubHandler $serverHandler;
    private MicropubClientHandler $clientHandler;

    public function __construct(
        Site $site,
        ?MicropubHandler $serverHandler = null,
        ?MicropubClientHandler $clientHandler = null
    ) {
        parent::__construct($site);
        $this->serverHandler = $serverHandler ?? new MicropubHandler($site);
        $this->clientHandler = $clientHandler ?? new MicropubClientHandler($site);
    }

    /**
     * Handles standard Micropub endpoint requests (POST create/media, GET config/syndicate-to).
     */
    public function handle(): void
    {
        $this->serverHandler->handle();
    }

    /**
     * Handles the Micropub local web admin posting client.
     */
    public function client(): void
    {
        $this->clientHandler->handle();
    }
}
