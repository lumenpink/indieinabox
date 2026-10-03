<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\Core\Container;
use Indieinabox\Search\Contracts\SearchServiceInterface;
use Indieinabox\Search\SearchService;
use Indieinabox\Site\Site;
use Indieinabox\Views\SearchView;

/**
 * Class SearchController
 *
 * Handles HTTP search requests for /search and /api/search with content negotiation (HTML and JSON).
 */
class SearchController
{
    private Site $site;
    private SearchServiceInterface $searchService;

    public function __construct(Site $site, ?SearchServiceInterface $searchService = null)
    {
        $this->site = $site;
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        $this->searchService = $searchService ?? ($container && $container->has(SearchServiceInterface::class)
            ? $container->get(SearchServiceInterface::class)
            : new SearchService(null, $site));
    }

    /**
     * Handles /search and /api/search requests.
     */
    public function handle(): void
    {
        $query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $type = !empty($_GET['type']) ? trim((string) $_GET['type']) : null;
        $lang = !empty($_GET['lang']) ? trim((string) $_GET['lang']) : null;
        $limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 20;
        $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

        $options = [
            'type' => $type,
            'lang' => $lang,
            'limit' => $limit,
            'offset' => $offset,
        ];

        $results = $this->searchService->search($query, $options);
        $results['type'] = $type;

        $isJson = (
            (isset($_GET['format']) && $_GET['format'] === 'json') ||
            (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
            (str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/search'))
        );

        if ($isJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo SearchView::render($results, $this->site);
    }
}
