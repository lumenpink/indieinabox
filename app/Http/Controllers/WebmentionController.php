<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\Services\WebmentionService;
use Indieinabox\Site;
use Indieinabox\Webmention\HelpPageView;

/**
 * Controller handling incoming webmentions and the webmention help form page.
 */
class WebmentionController extends AbstractController
{
    private WebmentionService $service;

    public function __construct(Site $site, ?WebmentionService $service = null)
    {
        parent::__construct($site);
        $this->service = $service ?? new WebmentionService();
    }

    /**
     * Dispatches incoming webmentions via POST requests or renders help form on GET requests.
     */
    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method !== 'POST') {
            $defaultTarget = rtrim($this->site->metadata->fqdn ?? '', '/') . '/';
            $this->htmlResponse(HelpPageView::render($defaultTarget), 200);
            return;
        }

        $source = $_POST['source'] ?? null;
        $target = $_POST['target'] ?? null;

        if (empty($source) || empty($target) || !is_string($source) || !is_string($target)) {
            $this->sendResponse(400, 'Missing source or target parameters.');
            return;
        }

        if (!filter_var($source, FILTER_VALIDATE_URL) || !filter_var($target, FILTER_VALIDATE_URL)) {
            $this->sendResponse(400, 'Invalid source or target URL format.');
            return;
        }

        $targetHost = parse_url($target, PHP_URL_HOST);
        $siteHost = parse_url($this->site->metadata->fqdn ?? '', PHP_URL_HOST);

        if (empty($targetHost) || empty($siteHost) || strcasecmp($targetHost, $siteHost) !== 0) {
            $this->sendResponse(400, 'Target URL does not belong to this site.');
            return;
        }

        if (strcasecmp($source, $target) === 0) {
            $this->sendResponse(400, 'Source and target URLs cannot be identical.');
            return;
        }

        if (!$this->service->isValidTarget($target, $this->site)) {
            $this->sendResponse(400, 'Target page not found on this site.');
            return;
        }

        $this->service->queue($source, $target);
        $this->sendResponse(202, 'Webmention accepted and queued for processing.');
    }

    protected function sendResponse(int $code, string $message): void
    {
        $this->jsonResponse([
            'status' => $code,
            'message' => $message,
        ], $code);
    }
}
