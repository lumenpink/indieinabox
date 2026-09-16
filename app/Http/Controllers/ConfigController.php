<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\Services\ConfigurationService;
use Indieinabox\Site;

/**
 * Controller specifically managing site and engine configuration settings in the admin dashboard.
 */
class ConfigController extends AbstractController
{
    private AdminController $adminController;
    private ConfigurationService $configService;

    public function __construct(
        Site $site,
        ?AdminController $adminController = null,
        ?ConfigurationService $configService = null
    ) {
        parent::__construct($site);
        $this->configService = $configService ?? new ConfigurationService();
        $this->adminController = $adminController ?? new AdminController($site, $this->configService);
    }

    public function getConfigurationService(): ConfigurationService
    {
        return $this->configService;
    }

    /**
     * Dispatches the config handling logic.
     */
    public function handle(): void
    {
        $this->adminController->config();
    }
}
