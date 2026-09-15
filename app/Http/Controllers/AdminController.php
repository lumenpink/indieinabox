<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\BackgroundWorker;
use Indieinabox\ConfigHandler;
use Indieinabox\MicropubClientHandler;
use Indieinabox\MicrosubReaderHandler;
use Indieinabox\ModerationHandler;
use Indieinabox\Site;

/**
 * Controller managing administrative panels (settings, config, client, reader, moderation, cron).
 */
class AdminController extends AbstractController
{
    private ConfigHandler $configHandler;
    private MicropubClientHandler $micropubClientHandler;
    private MicrosubReaderHandler $microsubReaderHandler;
    private ModerationHandler $moderationHandler;

    public function __construct(
        Site $site,
        ?ConfigHandler $configHandler = null,
        ?MicropubClientHandler $micropubClientHandler = null,
        ?MicrosubReaderHandler $microsubReaderHandler = null,
        ?ModerationHandler $moderationHandler = null
    ) {
        parent::__construct($site);
        $this->configHandler = $configHandler ?? new ConfigHandler($site);
        $this->micropubClientHandler = $micropubClientHandler ?? new MicropubClientHandler($site);
        $this->microsubReaderHandler = $microsubReaderHandler ?? new MicrosubReaderHandler($site);
        $this->moderationHandler = $moderationHandler ?? new ModerationHandler($site);
    }

    public function index(): void
    {
        $this->redirect('/admin/microsub');
    }

    public function config(): void
    {
        $this->configHandler->handle();
    }

    public function micropub(): void
    {
        $this->micropubClientHandler->handle();
    }

    public function microsub(): void
    {
        $this->microsubReaderHandler->handle();
    }

    public function moderation(): void
    {
        $this->moderationHandler->handle();
    }

    public function cron(): void
    {
        $worker = new BackgroundWorker($this->site);
        $worker->runAll();
        echo "OK";
    }
}
