<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\Site\Site;
use Indieinabox\Views\Client\UnifiedClientView;

/**
 * Class ClientController
 *
 * Serves the bespoke unified web client for Microsub reading and Micropub publishing (/client and /reader).
 */
class ClientController extends AbstractController
{
    public function __construct(Site $site)
    {
        parent::__construct($site);
    }

    /**
     * Renders the unified client interface.
     */
    public function handle(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_set_cookie_params(43200);
            @session_start();
        }

        $token = (string) ($_GET['token'] ?? ($_SESSION['admin_token'] ?? ''));

        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $context = [
            'micropub_endpoint' => $fqdn . '/micropub',
            'microsub_endpoint' => $fqdn . '/microsub',
            'token' => $token,
        ];

        $this->html(UnifiedClientView::render($context, $this->site));
    }
}
