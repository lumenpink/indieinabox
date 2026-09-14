<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\Micropub\MediaHandler;
use Indieinabox\Micropub\PostCreator;
use Indieinabox\Micropub\QueryHandler;

/**
 * Class MicropubHandler
 *
 * Orchestrates W3C Micropub API requests, validating authentication and delegating
 * queries, media uploads, and post creation to specialized handler classes.
 */
class MicropubHandler
{
    /**
     * @var Site Global site configuration and environment.
     */
    private Site $site;

    /**
     * @var IndieAuthHandler Authentication service.
     */
    private IndieAuthHandler $authHandler;

    /**
     * Initializes the MicropubHandler.
     *
     * @param Site $site Global site configuration and environment.
     * @param ?IndieAuthHandler $authHandler Optional authentication handler.
     */
    public function __construct(Site $site, ?IndieAuthHandler $authHandler = null)
    {
        $this->site = $site;
        $this->authHandler = $authHandler ?? new IndieAuthHandler($site);
    }

    /**
     * Main entry point for handling Micropub API requests.
     * Validates authentication and delegates to GET or POST specific handlers.
     *
     * @return void
     */
    public function handle(): void
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $requestUriClean = rtrim($requestUri, '/');

        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(43200);
            session_start();
        }

        // Verify Bearer Token or Admin Session
        $tokenData = $this->authHandler->validateBearerToken();
        if (!$tokenData && empty($_SESSION['admin_authenticated'])) {
            $this->sendResponse(401, 'Unauthorized', 'Missing or invalid Bearer token.');
            return;
        }

        // Endpoint: /micropub/media
        if ($requestUriClean === '/micropub/media') {
            $this->handleMediaEndpoint($tokenData ?? []);
            return;
        }

        // Endpoint: /micropub
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET') {
            $this->handleGetRequest();
            return;
        }

        if ($method === 'POST') {
            $this->handlePostRequest($tokenData ?? []);
            return;
        }

        $this->sendResponse(405, 'Method Not Allowed', 'Unsupported HTTP method.');
    }

    /**
     * Reads the raw input stream. Used for parsing JSON payloads.
     *
     * @return string The raw request body.
     */
    protected function getRawInput(): string
    {
        return (string) file_get_contents('php://input');
    }

    /**
     * Handles Micropub GET queries (e.g., config, source, syndicate-to).
     *
     * @return void
     */
    private function handleGetRequest(): void
    {
        $q = (string) ($_GET['q'] ?? '');
        $result = QueryHandler::handle($this->site, $q);

        if (isset($result['error'])) {
            $this->sendResponse($result['status'], $result['error'], $result['error_description'] ?? '');
            return;
        }

        $this->sendSuccessResponse($result['status'], $result['headers'] ?? [], $result['body'] ?? null);
    }

    /**
     * Handles Micropub POST requests for post creation.
     *
     * @param array<string, mixed> $tokenData
     * @return void
     */
    private function handlePostRequest(array $tokenData): void
    {
        $scopes = explode(' ', (string) ($tokenData['scope'] ?? ''));
        if (!empty($tokenData) && !in_array('create', $scopes, true)) {
            $this->sendResponse(403, 'Forbidden', 'The create scope is required.');
            return;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $input = [];

        if (strpos($contentType, 'application/json') === 0) {
            $json = $this->getRawInput();
            $data = json_decode($json, true) ?: [];
            if (!is_array($data)) {
                $this->sendResponse(400, 'Invalid JSON', 'Malformed JSON payload.');
                return;
            }

            $input['h'] = $data['type'][0] ?? 'entry';
            if (isset($data['type'])) {
                $input['h'] = str_replace('h-', '', $input['h']);
            }

            $properties = $data['properties'] ?? [];
            foreach ($properties as $key => $values) {
                if (is_array($values)) {
                    $input[$key] = count($values) === 1 ? $values[0] : $values;
                }
            }
        } else {
            $input = $_POST;
        }

        // Action routing (delete, undelete, update not fully implemented yet)
        $action = $input['action'] ?? 'create';
        if ($action !== 'create') {
            $this->sendResponse(400, 'Not Supported', 'Only create action is supported for now.');
            return;
        }

        $result = PostCreator::create($this->site, $input);
        $this->sendSuccessResponse($result['status'], $result['headers']);
    }

    /**
     * Handles file uploads to the Micropub media endpoint (/micropub/media).
     *
     * @param array<string, mixed> $tokenData
     * @return void
     */
    private function handleMediaEndpoint(array $tokenData): void
    {
        $scopes = explode(' ', (string) ($tokenData['scope'] ?? ''));
        if (!empty($tokenData) && !in_array('media', $scopes, true) && !in_array('create', $scopes, true)) {
            $this->sendResponse(403, 'Forbidden', 'The media or create scope is required.');
            return;
        }

        $file = $_FILES['file'] ?? [];
        $result = MediaHandler::handleUpload($this->site, $file, fn (string $src, string $dst) => $this->moveUploadedFile($src, $dst));

        if (isset($result['error'])) {
            $this->sendResponse($result['status'], $result['error'], $result['error_description'] ?? '');
            return;
        }

        $this->sendSuccessResponse($result['status'], $result['headers'] ?? []);
    }

    /**
     * Sends a successful HTTP response with headers.
     *
     * @param int $code HTTP status code.
     * @param array<string, string> $headers Headers to include in the response.
     * @param mixed $body Optional body content.
     * @return void
     */
    protected function sendSuccessResponse(int $code, array $headers = [], mixed $body = null): void
    {
        http_response_code($code);
        foreach ($headers as $key => $value) {
            header($key . ': ' . $value);
        }
        if ($body !== null) {
            echo is_string($body) ? $body : json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
    }

    /**
     * Sends a standard JSON-formatted HTTP error response.
     *
     * @param int $code HTTP status code.
     * @param string $error Short error identifier.
     * @param string $description Detailed error message.
     * @return void
     */
    protected function sendResponse(int $code, string $error, string $description): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => $error,
            'error_description' => $description,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Helper to move uploaded files to their destination.
     *
     * @param string $tmpName Path of the uploaded temporary file.
     * @param string $destPath Final destination path.
     * @return bool True on success, false on failure.
     */
    protected function moveUploadedFile(string $tmpName, string $destPath): bool
    {
        return move_uploaded_file($tmpName, $destPath);
    }
}
