<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\Site;
use Indieinabox\WebmentionHandler;

/**
 * Controller handling incoming webmentions and the webmention help form page.
 */
class WebmentionController extends AbstractController
{
    private WebmentionHandler $handler;

    public function __construct(Site $site, ?WebmentionHandler $handler = null)
    {
        parent::__construct($site);
        $this->handler = $handler ?? new WebmentionHandler($site);
    }

    /**
     * Dispatches the webmention request.
     */
    public function handle(): void
    {
        $this->handler->handle();
    }
}
