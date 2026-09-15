<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\ConfigHandler;
use Indieinabox\Site;

/**
 * Controller specifically managing site and engine configuration settings in the admin dashboard.
 */
class ConfigController extends AbstractController
{
    private ConfigHandler $handler;

    public function __construct(Site $site, ?ConfigHandler $handler = null)
    {
        parent::__construct($site);
        $this->handler = $handler ?? new ConfigHandler($site);
    }

    /**
     * Dispatches the config handling logic.
     */
    public function handle(): void
    {
        $this->handler->handle();
    }
}
