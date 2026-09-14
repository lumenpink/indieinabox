<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Webmention\HelpPageView;
use Indieinabox\Webmention\SourceVerifier;

/**
 * Class WebmentionHandler
 *
 * Handles incoming Webmention requests, validates endpoints, queues mentions,
 * and delegates source verification and view rendering to dedicated services.
 */
class WebmentionHandler
{
    /**
     * @var Site Global site configuration and environment.
     */
    private Site $site;

    /**
     * @var SourceVerifier Source link verification service.
     */
    private SourceVerifier $sourceVerifier;

    /**
     * Initializes the WebmentionHandler.
     *
     * @param Site $site Global site configuration and environment.
     * @param ?SourceVerifier $sourceVerifier Optional custom source verifier.
     */
    public function __construct(Site $site, ?SourceVerifier $sourceVerifier = null)
    {
        $this->site = $site;
        $this->sourceVerifier = $sourceVerifier ?? new SourceVerifier();
    }

    /**
     * Processes incoming webmentions via POST requests.
     * Validates source/target URIs, checks target file existence, and queues for moderation.
     * Renders help form on GET requests.
     *
     * @return void
     */
    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method !== 'POST') {
            $this->sendHelpPage();
            return;
        }

        $source = $_POST['source'] ?? null;
        $target = $_POST['target'] ?? null;

        if (empty($source) || empty($target)) {
            $this->sendResponse(400, 'Missing source or target parameters.');
            return;
        }

        // Validate URLs
        if (!filter_var($source, FILTER_VALIDATE_URL) || !filter_var($target, FILTER_VALIDATE_URL)) {
            $this->sendResponse(400, 'Invalid source or target URL format.');
            return;
        }

        // Validate target matches our domain/fqdn
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

        // Verify that target URL is a valid page on our site (exists in output dir)
        $targetPath = parse_url($target, PHP_URL_PATH) ?? '/';
        $sitePath = parse_url($this->site->metadata->fqdn ?? '', PHP_URL_PATH);
        if ($sitePath && $sitePath !== '/' && str_starts_with($targetPath, $sitePath)) {
            $targetPath = substr($targetPath, strlen($sitePath));
        }

        $base = rtrim($this->site->paths->baseDir, DIRECTORY_SEPARATOR);
        $outputDir = $this->site->paths->outputDirHtml;

        $targetPathClean = str_replace('..', '', urldecode($targetPath));
        if ($targetPathClean === '' || $targetPathClean === '/') {
            $targetFile = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . 'index.html';
        } else {
            $targetFile = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . trim($targetPathClean, '/') . DIRECTORY_SEPARATOR . 'index.html';
            if (!file_exists($targetFile)) {
                $targetFile = $base . DIRECTORY_SEPARATOR . $outputDir . DIRECTORY_SEPARATOR . trim($targetPathClean, '/');
            }
        }

        if (!file_exists($targetFile)) {
            $this->sendResponse(400, 'Target page not found on this site.');
            return;
        }

        // Queue Webmention for async verification and processing
        $this->queueWebmention($source, $target);

        $this->sendResponse(202, 'Webmention accepted and queued for processing.');
    }

    /**
     * Verifies that the source URL contains a link to target URL and extracts metadata.
     *
     * @param string $source
     * @param string $target
     * @return array{success: bool, message?: string, content?: array{title: string, text: string, whostyle?: array<array-key, mixed>|null}}
     */
    public function verifySourceLink(string $source, string $target): array
    {
        $verifier = new SourceVerifier(fn(string $url): string|false => $this->fetchUrl($url));
        return $verifier->verifySourceLink($source, $target);
    }

    /**
     * Allows overriding the URL fetcher for test mocking.
     *
     * @param string $url
     * @return string|false
     */
    protected function fetchUrl(string $url)
    {
        return $this->sourceVerifier->fetchUrl($url);
    }

    /**
     * Compares target and link href to check if they match (including relative links).
     *
     * @param string $href
     * @param string $target
     * @param string $source
     * @return bool
     */
    public function urlsMatch(string $href, string $target, string $source): bool
    {
        return $this->sourceVerifier->urlsMatch($href, $target, $source);
    }

    /**
     * Queues a webmention in inbox_queue for asynchronous background worker processing.
     *
     * @param string $source
     * @param string $target
     * @return void
     */
    public function queueWebmention(string $source, string $target): void
    {
        $db = Database::getDb();
        $payload = [
            'source' => $source,
            'target' => $target
        ];

        $sql = "INSERT INTO inbox_queue (type, payload_json, created_at) VALUES (?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute(['webmention', json_encode($payload), time()]);
    }

    /**
     * Sends a JSON HTTP response with a specific status code.
     *
     * @param int $code HTTP status code.
     * @param string $message Response message.
     * @return void
     */
    private function sendResponse(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $code,
            'message' => $message
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Renders HTML help page for the webmention endpoint (used on GET requests).
     *
     * @return void
     */
    private function sendHelpPage(): void
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        echo HelpPageView::render($this->site->metadata->fqdn ?? '');
    }
}
