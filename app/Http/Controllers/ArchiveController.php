<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\Services\ArchiveService;
use Indieinabox\Site;
use Indieinabox\Views\ArchiveView;

/**
 * Controller handling the web archive viewer and forced snapshot captures.
 */
class ArchiveController extends AbstractController
{
    private ArchiveService $service;

    public function __construct(Site $site, ?ArchiveService $service = null)
    {
        parent::__construct($site);
        $this->service = $service ?? new ArchiveService();
    }

    /**
     * Renders or serves the archive index and stored snapshots.
     */
    public function handle(): void
    {
        $url = $_GET['url'] ?? '';
        $ts = isset($_GET['ts']) ? (int) $_GET['ts'] : time();

        if ($url === '') {
            $this->htmlResponse('URL is required', 400);
            return;
        }

        $snapshot = $this->service->findSnapshot($url, $ts);
        $this->htmlResponse(ArchiveView::render($url, $snapshot));
    }

    /**
     * Triggers a forced archive snapshot.
     */
    public function force(): void
    {
        $url = $_POST['url'] ?? '';
        if ($url === '') {
            $this->htmlResponse('URL is required', 400);
            return;
        }

        $this->service->queueForceArchive($url);
        $this->redirectResponse('/archive?url=' . urlencode($url) . '&ts=' . time());
    }
}
