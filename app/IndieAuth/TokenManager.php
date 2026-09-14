<?php

declare(strict_types=1);

namespace Indieinabox\IndieAuth;

use PDO;
use Indieinabox\Database;

/**
 * Class TokenManager
 *
 * Manages creation, storage, validation, and exchange of IndieAuth authorization codes
 * and long-lived Bearer access tokens.
 */
class TokenManager
{
    /**
     * @var PDO Database connection.
     */
    private PDO $db;

    /**
     * TokenManager constructor.
     *
     * @param ?PDO $db Optional database connection.
     */
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getDb();
    }

    /**
     * Creates an authorization code and stores its SHA-256 hash.
     *
     * @param string $clientId Requesting client application identifier.
     * @param string $redirectUri Registered redirect URI.
     * @param string $me Canonical profile URL of the authenticated user.
     * @param string $scope Space-separated granted scopes.
     * @param ?string $codeChallenge Optional PKCE code challenge.
     * @param ?string $codeChallengeMethod Optional PKCE method ('S256' or 'plain').
     * @param int $ttl Lifetime in seconds (default 600s / 10 minutes).
     * @return string Plaintext authorization code.
     */
    public function createAuthorizationCode(
        string $clientId,
        string $redirectUri,
        string $me,
        string $scope,
        ?string $codeChallenge = null,
        ?string $codeChallengeMethod = null,
        ?string $state = null,
        int $ttl = 600
    ): string {
        $code = bin2hex(random_bytes(16));
        $now = time();
        $expiresAt = $now + $ttl;

        $stmt = $this->db->prepare(
            'INSERT INTO indieauth_codes ' .
            '(code_hash, client_id, redirect_uri, state, scope, code_challenge, code_challenge_method, expires_at, me) ' .
            'VALUES (:hash, :client_id, :redirect_uri, :state, :scope, :challenge, :method, :expires, :me)'
        );

        $stmt->bindValue(':hash', hash('sha256', $code), PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':redirect_uri', $redirectUri, PDO::PARAM_STR);
        $stmt->bindValue(':state', $state ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':scope', $scope, PDO::PARAM_STR);
        $stmt->bindValue(':challenge', $codeChallenge ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':method', $codeChallengeMethod ?? '', PDO::PARAM_STR);
        $stmt->bindValue(':expires', $expiresAt, PDO::PARAM_INT);
        $stmt->bindValue(':me', $me, PDO::PARAM_STR);
        $stmt->execute();

        return $code;
    }

    /**
     * Verifies an authorization code (for authentication-only flows) and consumes it.
     *
     * @param string $code Plaintext authorization code.
     * @param string $clientId Client ID verifying the code.
     * @param string $redirectUri Redirect URI.
     * @param ?string $codeVerifier Optional PKCE verifier.
     * @return array{me: string, scope: string}|array{error: string}
     */
    public function verifyAuthorizationCode(
        string $code,
        string $clientId,
        string $redirectUri,
        ?string $codeVerifier = null
    ): array {
        $codeData = $this->fetchAndConsumeCode($code);
        if (!$codeData) {
            return ['error' => 'Invalid or expired authorization code.'];
        }

        $validationError = $this->validateCodeParameters($codeData, $clientId, $redirectUri, $codeVerifier);
        if ($validationError !== null) {
            return ['error' => $validationError];
        }

        return [
            'me' => $codeData['me'],
            'scope' => $codeData['scope'],
        ];
    }

    /**
     * Exchanges an authorization code for a long-lived Bearer access token and consumes the code.
     *
     * @param string $code Plaintext authorization code.
     * @param string $clientId Client ID exchanging the code.
     * @param string $redirectUri Redirect URI.
     * @param ?string $codeVerifier Optional PKCE verifier.
     * @return array{access_token: string, me: string, scope: string, token_type: string}|array{error: string}
     */
    public function exchangeCodeForToken(
        string $code,
        string $clientId,
        string $redirectUri,
        ?string $codeVerifier = null
    ): array {
        $codeData = $this->fetchAndConsumeCode($code);
        if (!$codeData) {
            return ['error' => 'Invalid or expired authorization code.'];
        }

        $validationError = $this->validateCodeParameters($codeData, $clientId, $redirectUri, $codeVerifier);
        if ($validationError !== null) {
            return ['error' => $validationError];
        }

        $token = 'ia_' . bin2hex(random_bytes(24));
        $stmt = $this->db->prepare(
            'INSERT INTO indieauth_tokens (token_hash, client_id, scope, me, created_at) ' .
            'VALUES (:hash, :client_id, :scope, :me, :created)'
        );
        $stmt->bindValue(':hash', hash('sha256', $token), PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
        $stmt->bindValue(':scope', $codeData['scope'], PDO::PARAM_STR);
        $stmt->bindValue(':me', $codeData['me'], PDO::PARAM_STR);
        $stmt->bindValue(':created', time(), PDO::PARAM_INT);
        $stmt->execute();

        return [
            'access_token' => $token,
            'me' => $codeData['me'],
            'scope' => $codeData['scope'],
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Validates a provided Bearer token against stored valid tokens.
     *
     * @param ?string $tokenOut Reference to the token string if found.
     * @param ?string $authHeader Optional raw Authorization header.
     * @param ?string $queryToken Optional access_token from query.
     * @param ?string $postToken Optional access_token from POST.
     * @return array{me: string, client_id: string, scope: string}|null
     */
    public function validateBearerToken(
        ?string &$tokenOut = null,
        ?string $authHeader = null,
        ?string $queryToken = null,
        ?string $postToken = null
    ): ?array {
        $token = '';
        $header = $authHeader ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            $token = trim($matches[1]);
        } elseif ($queryToken !== null || isset($_GET['access_token'])) {
            $token = $queryToken ?? (string) $_GET['access_token'];
        } elseif ($postToken !== null || isset($_POST['access_token'])) {
            $token = $postToken ?? (string) $_POST['access_token'];
        }

        if ($token === '') {
            return null;
        }

        $tokenOut = $token;

        $stmt = $this->db->prepare('SELECT * FROM indieauth_tokens WHERE token_hash = :hash');
        $stmt->bindValue(':hash', hash('sha256', $token), PDO::PARAM_STR);
        $stmt->execute();
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData) {
            return null;
        }

        return [
            'me' => $tokenData['me'],
            'client_id' => $tokenData['client_id'],
            'scope' => $tokenData['scope'],
        ];
    }

    /**
     * Fetches authorization code details and deletes it immediately from storage.
     *
     * @param string $code
     * @return array<string, mixed>|null
     */
    private function fetchAndConsumeCode(string $code): ?array
    {
        $hash = hash('sha256', $code);
        $stmt = $this->db->prepare('SELECT * FROM indieauth_codes WHERE code_hash = :hash');
        $stmt->bindValue(':hash', $hash, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        // Delete immediately (one-time use code)
        $del = $this->db->prepare('DELETE FROM indieauth_codes WHERE code_hash = :hash');
        $del->bindValue(':hash', $hash, PDO::PARAM_STR);
        $del->execute();

        return $data;
    }

    /**
     * Validates expiration, client_id, redirect_uri, and PKCE parameters.
     *
     * @param array<string, mixed> $codeData
     * @param string $clientId
     * @param string $redirectUri
     * @param ?string $codeVerifier
     * @return string|null Error message or null on success.
     */
    private function validateCodeParameters(
        array $codeData,
        string $clientId,
        string $redirectUri,
        ?string $codeVerifier
    ): ?string {
        if (($codeData['expires_at'] ?? 0) < time()) {
            return 'Authorization code has expired.';
        }

        if (rtrim((string) $codeData['client_id'], '/') !== rtrim($clientId, '/')) {
            return 'Client ID mismatch.';
        }

        if (rtrim((string) $codeData['redirect_uri'], '/') !== rtrim($redirectUri, '/')) {
            return 'Redirect URI mismatch.';
        }

        if (!empty($codeData['code_challenge'])) {
            if (empty($codeVerifier)) {
                return 'Missing code_verifier for PKCE validation.';
            }

            $method = (string) ($codeData['code_challenge_method'] ?: 'plain');
            if (!PkceValidator::validate($codeVerifier, (string) $codeData['code_challenge'], $method)) {
                return 'PKCE verification failed.';
            }
        }

        return null;
    }
}
