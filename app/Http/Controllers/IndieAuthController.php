<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\IndieAuth\ConsentView;
use Indieinabox\IndieAuth\TokenManager;
use Indieinabox\Site\Site;

/**
 * Controller handling IndieAuth authentication, authorization code exchange, token issues, and metadata.
 */
class IndieAuthController extends AbstractController
{
    private TokenManager $tokenManager;

    public function __construct(Site $site, ?TokenManager $tokenManager = null)
    {
        parent::__construct($site);
        $this->tokenManager = $tokenManager ?? new TokenManager();
    }

    public function getTokenManager(): TokenManager
    {
        return $this->tokenManager;
    }

    /**
     * Dispatches IndieAuth request.
     */
    public function handle(): void
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $requestUriClean = rtrim($requestUri, '/');

        if ($requestUriClean === '/.well-known/oauth-authorization-server') {
            $this->sendMetadata();
            return;
        }

        if (preg_match('#/token$#i', $requestUriClean) === 1) {
            $this->handleTokenRequest();
            return;
        }

        if (preg_match('#/auth$#i', $requestUriClean) === 1) {
            $this->handleAuthRequest();
            return;
        }

        ConsentView::renderError(404, 'Endpoint not found.');
    }

    /**
     * Sends the OAuth 2.0 Authorization Server Metadata (JSON).
     */
    public function sendMetadata(): void
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

        $this->jsonResponse($metadata, 200);
    }

    /**
     * Handles the authorization endpoint (`/auth`).
     */
    public function handleAuthRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET') {
            ConsentView::renderLoginForm($this->site, $_GET);
            return;
        }

        if (isset($_POST['code'])) {
            $this->verifyAuthCode();
            return;
        }

        $this->processLogin();
    }

    /**
     * Processes submission of the user login form and issues an authorization code.
     */
    public function processLogin(): void
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
            ConsentView::renderLoginForm(
                $this->site,
                $_POST,
                'IndieAuth is not configured on this server (password is empty).'
            );
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

        $this->redirectResponse($location);
    }

    /**
     * Verifies an authorization code submitted by the client application.
     */
    public function verifyAuthCode(): void
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

        $this->jsonResponse($result, 200);
    }

    /**
     * Handles requests to the token endpoint (`/token`).
     */
    public function handleTokenRequest(): void
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
     */
    public function exchangeCodeForToken(): void
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

        $this->jsonResponse($result, 200);
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
     */
    public function verifyToken(): void
    {
        $tokenOut = null;
        $tokenData = $this->validateBearerToken($tokenOut);

        if ($tokenData === null) {
            ConsentView::renderError(401, 'Unauthorized. Invalid or missing access token.');
            return;
        }

        $this->jsonResponse([
            'me' => $tokenData['me'],
            'client_id' => $tokenData['client_id'],
            'scope' => $tokenData['scope'],
        ], 200);
    }
}
