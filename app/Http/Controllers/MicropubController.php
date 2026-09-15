<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\IndieAuth\TokenManager;
use Indieinabox\Micropub\MediaHandler;
use Indieinabox\Micropub\PostCreator;
use Indieinabox\Micropub\QueryHandler;
use Indieinabox\MicropubClientHandler;
use Indieinabox\Site;

/**
 * Controller handling Micropub server queries, post creation, media uploads, and the web-based posting client.
 */
class MicropubController extends AbstractController
{
    private TokenManager $tokenManager;
    private MicropubClientHandler $clientHandler;

    public function __construct(
        Site $site,
        ?TokenManager $tokenManager = null,
        ?MicropubClientHandler $clientHandler = null
    ) {
        parent::__construct($site);
        $this->tokenManager = $tokenManager ?? new TokenManager();
        $this->clientHandler = $clientHandler ?? new MicropubClientHandler($site);
    }

    /**
     * Handles standard Micropub endpoint requests (POST create/media, GET config/syndicate-to).
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
        $tokenData = $this->tokenManager->validateBearerToken();
        if (!$tokenData && empty($_SESSION['admin_authenticated'])) {
            $this->sendErrorResponse(401, 'Unauthorized', 'Missing or invalid Bearer token.');
            return;
        }

        // Endpoint: /micropub/media
        if ($requestUriClean === '/micropub/media') {
            $this->handleMediaEndpoint($tokenData ?? []);
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET') {
            $this->handleGetRequest();
            return;
        }

        if ($method === 'POST') {
            $this->handlePostRequest($tokenData ?? []);
            return;
        }

        $this->sendErrorResponse(405, 'Method Not Allowed', 'Unsupported HTTP method.');
    }

    /**
     * Handles the Micropub local web admin posting client.
     */
    public function client(): void
    {
        $this->clientHandler->handle();
    }

    protected function handleGetRequest(): void
    {
        $q = (string) ($_GET['q'] ?? '');
        $result = QueryHandler::handle($this->site, $q);

        if (isset($result['error'])) {
            $this->sendErrorResponse($result['status'], $result['error'], $result['error_description'] ?? '');
            return;
        }

        $this->sendSuccessResponse($result['status'], $result['headers'] ?? [], $result['body'] ?? null);
    }

    /**
     * @param array<string, mixed> $tokenData
     */
    protected function handlePostRequest(array $tokenData): void
    {
        $scopes = explode(' ', (string) ($tokenData['scope'] ?? ''));
        if (!empty($tokenData) && !in_array('create', $scopes, true)) {
            $this->sendErrorResponse(403, 'Forbidden', 'The create scope is required.');
            return;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $input = [];

        if (str_starts_with($contentType, 'application/json')) {
            $json = $this->getRawInput();
            $data = json_decode($json, true) ?: [];
            if (!is_array($data)) {
                $this->sendErrorResponse(400, 'Invalid JSON', 'Malformed JSON payload.');
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

        $action = $input['action'] ?? 'create';
        if ($action !== 'create') {
            $this->sendErrorResponse(400, 'Not Supported', 'Only create action is supported for now.');
            return;
        }

        $result = PostCreator::create($this->site, $input);
        $this->sendSuccessResponse($result['status'], $result['headers']);
    }

    /**
     * @param array<string, mixed> $tokenData
     */
    protected function handleMediaEndpoint(array $tokenData): void
    {
        $scopes = explode(' ', (string) ($tokenData['scope'] ?? ''));
        if (!empty($tokenData) && !in_array('media', $scopes, true) && !in_array('create', $scopes, true)) {
            $this->sendErrorResponse(403, 'Forbidden', 'The media or create scope is required.');
            return;
        }

        $file = $_FILES['file'] ?? [];
        $result = MediaHandler::handleUpload(
            $this->site,
            $file,
            fn (string $src, string $dst) => $this->moveUploadedFile($src, $dst)
        );

        if (isset($result['error'])) {
            $this->sendErrorResponse($result['status'], $result['error'], $result['error_description'] ?? '');
            return;
        }

        $this->sendSuccessResponse($result['status'], $result['headers'] ?? []);
    }

    protected function getRawInput(): string
    {
        return (string) file_get_contents('php://input');
    }

    protected function moveUploadedFile(string $tmpName, string $destPath): bool
    {
        return move_uploaded_file($tmpName, $destPath);
    }

    /**
     * @param int $code
     * @param array<string, string> $headers
     * @param mixed $body
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

    protected function sendErrorResponse(int $code, string $error, string $description): void
    {
        $this->jsonResponse([
            'error' => $error,
            'error_description' => $description,
        ], $code);
    }
}
