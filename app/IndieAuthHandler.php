<?php

declare(strict_types=1);

namespace Indieinabox;

use Indieinabox\IndieAuth\ConsentView;
use Indieinabox\IndieAuth\PkceValidator;
use Indieinabox\IndieAuth\TokenManager;

/**
 * Class IndieAuthHandler
 *
 * Orchestrates IndieAuth and OAuth 2.0 endpoints (Metadata discovery, Authorization, Token exchange)
 * delegating cryptographic PKCE verification to PkceValidator, lifecycle storage to TokenManager,
 * and presentation templates to ConsentView.
 */
class IndieAuthHandler
{
    /**
     * @var Site Global site configuration and environment.
     */
    private Site $site;

    /**
     * @var TokenManager Token and code management service.
     */
    private TokenManager $tokenManager;

    /**
     * Initializes the IndieAuthHandler and binds dependencies.
     *
     * @param Site $site Global site configuration.
     * @param ?TokenManager $tokenManager Optional token manager service.
     */
    public function __construct(Site $site, ?TokenManager $tokenManager = null)
    {
        $this->site = $site;
        $this->tokenManager = $tokenManager ?? new TokenManager();
    }

    /**
     * Main entry point for IndieAuth requests.
     * Routes the request to metadata, token exchange, or authorization endpoints.
     *
     * @return void
     */
    public function handle(): void
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $requestUriClean = rtrim($requestUri, '/');

        // Route: Metadata Discovery
        if ($requestUriClean === '/.well-known/oauth-authorization-server') {
            $this->sendMetadata();
            return;
        }

        // Route: Token Endpoint
        if (preg_match('#/token$#i', $requestUriClean) === 1) {
            $this->handleTokenRequest();
            return;
        }

        // Route: Authorization Endpoint
        if (preg_match('#/auth$#i', $requestUriClean) === 1) {
            $this->handleAuthRequest();
            return;
        }

        ConsentView::renderError(404, 'Endpoint not found.');
    }

    /**
     * Sends the OAuth 2.0 Authorization Server Metadata (JSON).
     *
     * @return void
     */
    private function sendMetadata(): void
    {
        $fqdn = rtrim($this->site->metadata->fqdn ?? 'http://localhost:8080', '/');

        $metadata = [
            'issuer' => $fqdn . '/',
            'authorization_endpoint' => $fqdn . '/auth',
            'token_endpoint' => $fqdn . '/token',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code'],
            'code_challenge_methods_supported' => ['S256', 'plain'],
        ];

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handles the authorization endpoint (`/auth`).
     *
     * @return void
     */
    private function handleAuthRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET') {
            ConsentView::renderLoginForm($this->site, $_GET);
            return;
        }

        // Client authorization code verification (POST with code)
        if (isset($_POST['code'])) {
            $this->verifyAuthCode();
            return;
        }

        // User login form submission
        $this->processLogin();
    }

    /**
     * Processes submission of the user login form and issues an authorization code.
     *
     * @return void
     */
    private function processLogin(): void
    {
        $clientId = $_POST['client_id'] ?? '';
        $redirectUri = $_POST['redirect_uri'] ?? '';
        $state = $_POST['state'] ?? '';
        $scope = $_POST['scope'] ?? '';
        $codeChallenge = $_POST['code_challenge'] ?? null;
        $codeChallengeMethod = $_POST['code_challenge_method'] ?? null;
        $password = $_POST['password'] ?? '';

        $configuredPassword = $this->site->metadata->indieauthPassword;

        if (empty($configuredPassword)) {
            ConsentView::renderLoginForm($this->site, $_POST, 'IndieAuth is not configured on this server (password is empty).');
            return;
        }

        $isValid = ($password === $configuredPassword) || password_verify($password, $configuredPassword);
        if (!$isValid) {
            ConsentView::renderLoginForm($this->site, $_POST, 'Invalid password.');
            return;
        }

        $me = rtrim($this->site->metadata->fqdn ?? '', '/') . '/';
        $code = $this->tokenManager->createAuthorizationCode(
            $clientId,
            $redirectUri,
            $me,
            $scope,
            $codeChallenge,
            $codeChallengeMethod,
            $state
        );

        $joinChar = (strpos($redirectUri, '?') === false) ? '?' : '&';
        $location = $redirectUri . $joinChar . 'code=' . urlencode($code) . '&state=' . urlencode($state);

        http_response_code(302);
        header('Location: ' . $location);
    }

    /**
     * Verifies an authorization code submitted by the client application.
     *
     * @return void
     */
    private function verifyAuthCode(): void
    {
        $code = $_POST['code'] ?? '';
        $clientId = $_POST['client_id'] ?? '';
        $redirectUri = $_POST['redirect_uri'] ?? '';
        $codeVerifier = $_POST['code_verifier'] ?? null;

        $result = $this->tokenManager->verifyAuthorizationCode($code, $clientId, $redirectUri, $codeVerifier);
        if (isset($result['error'])) {
            ConsentView::renderError(400, $result['error']);
            return;
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handles requests to the token endpoint (`/token`).
     *
     * @return void
     */
    private function handleTokenRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'POST') {
            $grantType = $_POST['grant_type'] ?? '';
            if ($grantType === 'authorization_code') {
                $this->exchangeCodeForToken();
                return;
            }
            ConsentView::renderError(400, 'Unsupported grant_type.');
            return;
        }

        $this->verifyToken();
    }

    /**
     * Exchanges an authorization code for a Bearer access token.
     *
     * @return void
     */
    private function exchangeCodeForToken(): void
    {
        $code = $_POST['code'] ?? '';
        $clientId = $_POST['client_id'] ?? '';
        $redirectUri = $_POST['redirect_uri'] ?? '';
        $codeVerifier = $_POST['code_verifier'] ?? null;

        $result = $this->tokenManager->exchangeCodeForToken($code, $clientId, $redirectUri, $codeVerifier);
        if (isset($result['error'])) {
            ConsentView::renderError(400, $result['error']);
            return;
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Validates a provided Bearer token against stored valid tokens.
     *
     * @param ?string $tokenOut Reference to the token string if found.
     * @return array{me: string, client_id: string, scope: string}|null
     */
    public function validateBearerToken(?string &$tokenOut = null): ?array
    {
        return $this->tokenManager->validateBearerToken($tokenOut);
    }

    /**
     * Verifies the provided token via a GET request to the token endpoint.
     *
     * @return void
     */
    private function verifyToken(): void
    {
        $tokenOut = null;
        $tokenData = $this->validateBearerToken($tokenOut);

        if ($tokenData === null) {
            ConsentView::renderError(401, 'Unauthorized. Invalid or missing access token.');
            return;
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'me' => $tokenData['me'],
            'client_id' => $tokenData['client_id'],
            'scope' => $tokenData['scope'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
