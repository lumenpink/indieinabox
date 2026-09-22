<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\Core\Container;
use Indieinabox\Discovery\Contracts\DiscoveryServiceInterface;
use Indieinabox\Discovery\DiscoveryService;
use Indieinabox\Site\Site;
use Indieinabox\Views\DiscoveryView;
use XMLWriter;

/**
 * Class DiscoveryController
 *
 * Handles HTTP requests for /discover and /global endpoints with content negotiation.
 */
class DiscoveryController
{
    private Site $site;
    private DiscoveryServiceInterface $discoveryService;

    public function __construct(Site $site, ?DiscoveryServiceInterface $discoveryService = null)
    {
        $this->site = $site;
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        $this->discoveryService = $discoveryService ?? ($container && $container->has(DiscoveryServiceInterface::class)
            ? $container->get(DiscoveryServiceInterface::class)
            : new DiscoveryService(null, $site));
    }

    /**
     * Main handler for /discover and /global routes.
     */
    public function handle(): void
    {
        $network = !empty($_GET['network']) ? trim((string) $_GET['network']) : null;
        $tag = !empty($_GET['tag']) ? trim((string) $_GET['tag']) : null;
        $search = !empty($_GET['q']) ? trim((string) $_GET['q']) : null;
        $limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 20;
        $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

        $filters = [];
        if ($network !== null) {
            $filters['network'] = $network;
        }
        if ($tag !== null) {
            $filters['tag'] = $tag;
        }
        if ($search !== null) {
            $filters['search'] = $search;
        }

        $result = $this->discoveryService->getTimeline($filters, $limit, $offset);

        // Determine output format
        $format = $this->determineFormat();

        if ($format === 'jf2' || $format === 'json') {
            $this->respondJson($result, $filters, $limit, $offset);
            return;
        }

        if ($format === 'rss') {
            $this->respondRss($result);
            return;
        }

        $this->respondHtml($result, $filters, $limit, $offset);
    }

    private function determineFormat(): string
    {
        if (isset($_GET['format'])) {
            $f = strtolower(trim((string) $_GET['format']));
            if (in_array($f, ['json', 'jf2', 'rss'], true)) {
                return $f;
            }
        }

        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        if (str_contains($accept, 'application/jf2+json')) {
            return 'jf2';
        }
        if (str_contains($accept, 'application/json')) {
            return 'json';
        }
        if (str_contains($accept, 'application/rss+xml') || str_contains($accept, 'application/xml')) {
            return 'rss';
        }

        return 'html';
    }

    /**
     * Responds with JF2 JSON.
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $filters
     */
    private function respondJson(array $result, array $filters, int $limit, int $offset): void
    {
        header('Content-Type: application/jf2+json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');

        $siteFqdn = rtrim($this->site->metadata->fqdn ?? 'http://localhost', '/');

        $children = array_map(function (array $item): array {
            return [
                'type' => 'entry',
                'uid' => $item['id'] ?? '',
                'url' => $item['url'] ?? '',
                'name' => $item['title'] ?? '',
                'published' => $item['published'] ?? '',
                'author' => [
                    'type' => 'card',
                    'name' => $item['author']['name'] ?? '',
                    'url' => $item['author']['url'] ?? '',
                    'photo' => $item['author']['photo'] ?? '',
                ],
                'content' => [
                    'text' => $item['content']['text'] ?? '',
                    'html' => $item['content']['html'] ?? '',
                ],
                'category' => $item['tags'] ?? [],
                'photo' => $item['photos'] ?? [],
                '_indieinabox' => [
                    'network' => $item['network'] ?? 'local',
                    'origin_server' => $item['origin_server'] ?? '',
                    'content_warning' => $item['content_warning'] ?? null,
                    'poll' => $item['poll'] ?? null,
                ],
            ];
        }, $result['items'] ?? []);

        echo json_encode([
            'type' => 'feed',
            'name' => 'Global Timeline - ' . ($this->site->metadata->sitename ?? 'indieinabox'),
            'url' => $siteFqdn . '/discover',
            'children' => $children,
            'total' => $result['total'] ?? 0,
            'limit' => $limit,
            'offset' => $offset,
            'networks' => $result['networks'] ?? [],
            'tags' => $result['tags'] ?? [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Responds with RSS 2.0 XML.
     *
     * @param array<string, mixed> $result
     */
    private function respondRss(array $result): void
    {
        header('Content-Type: application/rss+xml; charset=utf-8');

        $siteFqdn = rtrim($this->site->metadata->fqdn ?? 'http://localhost', '/');
        $siteTitle = $this->site->metadata->sitename ?? 'indieinabox';

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');

        $xml->startElement('channel');
        $xml->writeElement('title', 'Global Timeline - ' . $siteTitle);
        $xml->writeElement('description', 'Public aggregated timeline of federated updates');
        $xml->writeElement('link', $siteFqdn . '/discover');
        $xml->writeElement('generator', 'indieinabox');
        $xml->writeElement('lastBuildDate', gmdate('r'));

        // Atom self link
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', $siteFqdn . '/discover?format=rss');
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();

        foreach ($result['items'] ?? [] as $item) {
            $xml->startElement('item');
            $title = !empty($item['title']) ? (string)$item['title'] : mb_substr((string)($item['content']['text'] ?? 'Post'), 0, 50, 'UTF-8');
            $xml->writeElement('title', $title);
            $xml->writeElement('link', (string)($item['url'] ?? $siteFqdn));

            $guid = !empty($item['url']) ? (string)$item['url'] : (string)($item['id'] ?? '');
            $xml->startElement('guid');
            $xml->writeAttribute('isPermaLink', !empty($item['url']) ? 'true' : 'false');
            $xml->text($guid);
            $xml->endElement();

            $pubTs = (int)($item['published_ts'] ?? 0);
            if ($pubTs > 0) {
                $xml->writeElement('pubDate', gmdate('r', $pubTs));
            }

            if (!empty($item['author']['name'])) {
                $xml->writeElement('author', (string)$item['author']['name']);
            }

            if (!empty($item['content']['html'])) {
                $xml->startElement('content:encoded');
                $xml->writeCdata((string)$item['content']['html']);
                $xml->endElement();
            }

            $xml->endElement(); // item
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss

        echo $xml->outputMemory();
    }

    /**
     * Responds with HTML.
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $filters
     */
    private function respondHtml(array $result, array $filters, int $limit, int $offset): void
    {
        header('Content-Type: text/html; charset=utf-8');

        echo DiscoveryView::render([
            'items' => $result['items'] ?? [],
            'total' => $result['total'] ?? 0,
            'networks' => $result['networks'] ?? [],
            'tags' => $result['tags'] ?? [],
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ], $this->site);
    }
}
