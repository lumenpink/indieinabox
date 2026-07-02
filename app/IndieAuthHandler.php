<?php

declare(strict_types=1);

namespace Indieinabox;

/**
 * Class IndieAuthHandler
 */
class IndieAuthHandler
{
    /**
     * @var Indieinabox\Site
     */
    private Site $site;

    /**
     * Method __construct
     * @param Indieinabox\Site $site
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Method handle
     * @return void
     */
    public function handle(): void
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $requestUriClean = rtrim($requestUri, '/');

        // Route: Metadata
        if ($requestUriClean === '/.well-known/oauth-authorization-server') {
            $this->sendMetadata();
            return;
        }

        // Route: Token
        $isTokenParam = isset($_GET['token']);
        $isTokenPath = (preg_match('#/token$#i', $requestUriClean) === 1);
        if ($isTokenParam || $isTokenPath) {
            $this->handleTokenRequest();
            return;
        }

        // Route: Auth
        $isAuthParam = isset($_GET['auth']);
        $isAuthPath = (preg_match('#/auth$#i', $requestUriClean) === 1);
        if ($isAuthParam || $isAuthPath) {
            $this->handleAuthRequest();
            return;
        }

        $this->sendResponse(404, 'Endpoint not found.');
    }

    /**
     * Method sendMetadata
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
            'code_challenge_methods_supported' => ['S256', 'plain']
        ];

        header('HTTP/1.1 200 OK');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Method handleAuthRequest
     * @return void
     */
    private function handleAuthRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET') {
            $this->renderLoginForm();
            return;
        }

        // POST request
        // Check if it's client verification (verification POST has code)
        if (isset($_POST['code'])) {
            $this->verifyAuthCode();
            return;
        }

        // Otherwise it is the login form submission
        $this->processLogin();
    }

    /**
     * Method renderLoginForm
     * @param ?string $error
     * 
     * @return void
     */
    private function renderLoginForm(?string $error = null): void
    {
        $clientId = $_GET['client_id'] ?? $_POST['client_id'] ?? '';
        $redirectUri = $_GET['redirect_uri'] ?? $_POST['redirect_uri'] ?? '';
        $state = $_GET['state'] ?? $_POST['state'] ?? '';
        $scope = $_GET['scope'] ?? $_POST['scope'] ?? '';
        $codeChallenge = $_GET['code_challenge'] ?? $_POST['code_challenge'] ?? '';
        $codeChallengeMethod = $_GET['code_challenge_method'] ?? $_POST['code_challenge_method'] ?? '';

        if (empty($clientId) || empty($redirectUri)) {
            $this->sendResponse(400, 'Missing client_id or redirect_uri parameters.');
            return;
        }

        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');

        header('HTTP/1.1 200 OK');
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>IndieAuth Sign In</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
            <style>
                :root {
                    --bg-gradient: linear-gradient(135deg, #090d16 0%, #111827 50%, #1e1b4b 100%);
                    --card-bg: rgba(17, 24, 39, 0.7);
                    --accent: #eccb00;
                    --accent-glow: rgba(236, 203, 0, 0.35);
                    --text-primary: #f9fafb;
                    --text-secondary: #9ca3af;
                    --border: rgba(255, 255, 255, 0.08);
                    --input-bg: rgba(3, 7, 18, 0.6);
                    --input-focus: rgba(236, 203, 0, 0.15);
                    --error-color: #ef4444;
                }

                body {
                    font-family: 'Outfit', sans-serif;
                    background: var(--bg-gradient);
                    background-attachment: fixed;
                    color: var(--text-primary);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0;
                    padding: 2rem 1.5rem;
                    box-sizing: border-box;
                }

                .container {
                    backdrop-filter: blur(20px);
                    -webkit-backdrop-filter: blur(20px);
                    background: var(--card-bg);
                    border: 1px solid var(--border);
                    border-radius: 28px;
                    padding: 3rem;
                    max-width: 540px;
                    width: 100%;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7),
                                0 0 50px rgba(236, 203, 0, 0.03);
                    position: relative;
                    overflow: hidden;
                    transition: transform 0.3s ease, box-shadow 0.3s ease;
                }

                .container::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: linear-gradient(90deg, #eccb00, #f59e0b);
                }

                h1 {
                    font-size: 2rem;
                    font-weight: 800;
                    margin-top: 0;
                    margin-bottom: 0.5rem;
                    background: linear-gradient(90deg, #ffffff, #eccb00);
                    -webkit-background-clip: text;
                    background-clip: text;
                    -webkit-text-fill-color: transparent;
                    letter-spacing: -0.02em;
                }

                .subtitle {
                    color: var(--text-secondary);
                    font-size: 1rem;
                    line-height: 1.5;
                    margin-bottom: 2rem;
                }

                .error-message {
                    background: rgba(239, 68, 68, 0.1);
                    border: 1px solid rgba(239, 68, 68, 0.2);
                    border-radius: 12px;
                    padding: 0.85rem 1rem;
                    font-size: 0.95rem;
                    color: var(--error-color);
                    margin-bottom: 1.5rem;
                }

                .app-card {
                    background: rgba(3, 7, 18, 0.4);
                    border: 1px solid var(--border);
                    border-radius: 16px;
                    padding: 1.25rem;
                    margin-bottom: 2rem;
                    font-size: 0.95rem;
                    line-height: 1.5;
                }

                .app-card div {
                    margin-bottom: 0.5rem;
                }

                .app-card div:last-child {
                    margin-bottom: 0;
                }

                .label-title {
                    color: var(--text-secondary);
                    font-weight: 600;
                    font-size: 0.8rem;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }

                .label-value {
                    font-family: 'JetBrains Mono', monospace;
                    font-size: 0.9rem;
                    color: var(--accent);
                    word-break: break-all;
                }

                form {
                    display: flex;
                    flex-direction: column;
                    gap: 1.5rem;
                }

                .form-group {
                    display: flex;
                    flex-direction: column;
                    gap: 0.5rem;
                }

                label {
                    font-weight: 600;
                    font-size: 0.9rem;
                    color: var(--text-secondary);
                }

                input[type="password"] {
                    font-family: inherit;
                    background: var(--input-bg);
                    border: 1px solid var(--border);
                    border-radius: 12px;
                    padding: 0.85rem 1rem;
                    font-size: 1rem;
                    color: var(--text-primary);
                    transition: all 0.2s ease;
                }

                input[type="password"]:focus {
                    outline: none;
                    border-color: var(--accent);
                    box-shadow: 0 0 0 4px var(--input-focus);
                    background: rgba(3, 7, 18, 0.8);
                }

                .button-group {
                    display: flex;
                    gap: 1rem;
                    margin-top: 1rem;
                }

                button {
                    flex: 2;
                    font-family: inherit;
                    background: linear-gradient(135deg, #eccb00 0%, #d8b600 100%);
                    color: #030712;
                    border: none;
                    padding: 0.95rem 1.5rem;
                    border-radius: 12px;
                    font-size: 1.05rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    box-shadow: 0 4px 12px var(--accent-glow);
                }

                button:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 6px 20px var(--accent-glow);
                    background: linear-gradient(135deg, #fce029 0%, #eccb00 100%);
                }

                .btn-cancel {
                    flex: 1;
                    background: rgba(31, 41, 55, 0.6);
                    color: var(--text-primary);
                    border: 1px solid var(--border);
                    text-decoration: none;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 12px;
                    font-weight: 600;
                    font-size: 1.05rem;
                    box-shadow: none;
                    transition: all 0.2s ease;
                }

                .btn-cancel:hover {
                    background: rgba(55, 65, 81, 0.8);
                    border-color: var(--text-secondary);
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>IndieAuth Request</h1>
                <p class="subtitle">Authenticate yourself to gain access to the requesting application.</p>

                <?php if ($error): ?>
                    <div class="error-message"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="app-card">
                    <div>
                        <div class="label-title">Application (Client ID)</div>
                        <div class="label-value"><?= htmlspecialchars($clientId) ?></div>
                    </div>
                    <div>
                        <div class="label-title">Your Identity</div>
                        <div class="label-value"><?= htmlspecialchars($fqdn . '/') ?></div>
                    </div>
                    <?php if ($scope): ?>
                        <div>
                            <div class="label-title">Requested Scopes</div>
                            <div class="label-value" style="color:#38bdf8;"><?= htmlspecialchars($scope) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <form action="" method="POST">
                    <input type="hidden" name="client_id" value="<?= htmlspecialchars($clientId) ?>">
                    <input type="hidden" name="redirect_uri" value="<?= htmlspecialchars($redirectUri) ?>">
                    <input type="hidden" name="state" value="<?= htmlspecialchars($state) ?>">
                    <input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>">
                    <input type="hidden" name="code_challenge" value="<?= htmlspecialchars($codeChallenge) ?>">
                    <input type="hidden" name="code_challenge_method" value="<?= htmlspecialchars($codeChallengeMethod) ?>">

                    <div class="form-group">
                        <label for="password">Enter Password</label>
                        <input type="password" name="password" id="password" required placeholder="••••••••" autofocus>
                    </div>

                    <div class="button-group">
                        <a href="<?= htmlspecialchars($redirectUri) ?>?error=access_denied&state=<?= htmlspecialchars($state) ?>" class="btn-cancel">Cancel</a>
                        <button type="submit">Authorize</button>
                    </div>
                </form>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Method processLogin
     * @return void
     */
    private function processLogin(): void
    {
        $clientId = $_POST['client_id'] ?? '';
        $redirectUri = $_POST['redirect_uri'] ?? '';
        $state = $_POST['state'] ?? '';
        $scope = $_POST['scope'] ?? '';
        $codeChallenge = $_POST['code_challenge'] ?? '';
        $codeChallengeMethod = $_POST['code_challenge_method'] ?? 'plain';
        $password = $_POST['password'] ?? '';

        $configuredPassword = $this->site->metadata->indieauthPassword;

        if (empty($configuredPassword)) {
            $this->renderLoginForm('IndieAuth is not configured on this server (password is empty).');
            return;
        }

        $isValid = ($password === $configuredPassword) || password_verify($password, $configuredPassword);

        if (!$isValid) {
            $this->renderLoginForm('Invalid password.');
            return;
        }

        // Generate auth code
        $code = bin2hex(random_bytes(16));
        $expiresAt = time() + 600; // 10 mins
        $me = rtrim($this->site->metadata->fqdn ?? '', '/') . '/';

        $db = \Indieinabox\Database::getDb();
        $stmt = $db->prepare('INSERT INTO indieauth_codes (code_hash, client_id, redirect_uri, state, scope, code_challenge, code_challenge_method, expires_at, me) VALUES (:hash, :client_id, :redirect_uri, :state, :scope, :challenge, :method, :expires, :me)');
        $stmt->bindValue(':hash', hash('sha256', $code), \PDO::PARAM_STR);
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_STR);
        $stmt->bindValue(':redirect_uri', $redirectUri, \PDO::PARAM_STR);
        $stmt->bindValue(':state', $state, \PDO::PARAM_STR);
        $stmt->bindValue(':scope', $scope, \PDO::PARAM_STR);
        $stmt->bindValue(':challenge', $codeChallenge, \PDO::PARAM_STR);
        $stmt->bindValue(':method', $codeChallengeMethod, \PDO::PARAM_STR);
        $stmt->bindValue(':expires', $expiresAt, \PDO::PARAM_INT);
        $stmt->bindValue(':me', $me, \PDO::PARAM_STR);
        $stmt->execute();

        // Redirect back with code and state
        $joinChar = (strpos($redirectUri, '?') === false) ? '?' : '&';
        $location = $redirectUri . $joinChar . 'code=' . urlencode($code) . '&state=' . urlencode($state);

        header('HTTP/1.1 302 Found');
        header('Location: ' . $location);
    }

    /**
     * Method verifyAuthCode
     * @return void
     */
    private function verifyAuthCode(): void
    {
        $code = $_POST['code'] ?? '';
        $clientId = $_POST['client_id'] ?? '';
        $redirectUri = $_POST['redirect_uri'] ?? '';
        $codeVerifier = $_POST['code_verifier'] ?? '';

        $db = \Indieinabox\Database::getDb();
        $stmt = $db->prepare('SELECT * FROM indieauth_codes WHERE code_hash = :hash');
        $stmt->bindValue(':hash', hash('sha256', $code), \PDO::PARAM_STR);
        $stmt->execute();
        $codeData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$codeData) {
            $this->sendResponse(400, 'Invalid or expired authorization code.');
            return;
        }

        // Cleanup code (use once)
        $delStmt = $db->prepare('DELETE FROM indieauth_codes WHERE code_hash = :hash');
        $delStmt->bindValue(':hash', hash('sha256', $code), \PDO::PARAM_STR);
        $delStmt->execute();

        if ($codeData['expires_at'] < time()) {
            $this->sendResponse(400, 'Authorization code has expired.');
            return;
        }

        if (rtrim($codeData['client_id'], '/') !== rtrim($clientId, '/')) {
            $this->sendResponse(400, 'Client ID mismatch.');
            return;
        }

        if (rtrim($codeData['redirect_uri'], '/') !== rtrim($redirectUri, '/')) {
            $this->sendResponse(400, 'Redirect URI mismatch.');
            return;
        }

        // Verify PKCE
        if (!empty($codeData['code_challenge'])) {
            if (empty($codeVerifier)) {
                $this->sendResponse(400, 'Missing code_verifier for PKCE validation.');
                return;
            }

            $method = strtolower($codeData['code_challenge_method'] ?: 'plain');
            if ($method === 's256') {
                $challengeCalculated = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash('sha256', $codeVerifier, true)));
            } else {
                $challengeCalculated = $codeVerifier;
            }

            if (!hash_equals($codeData['code_challenge'], $challengeCalculated)) {
                $this->sendResponse(400, 'PKCE verification failed.');
                return;
            }
        }

        header('HTTP/1.1 200 OK');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'me' => $codeData['me'],
            'scope' => $codeData['scope']
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Method handleTokenRequest
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
            $this->sendResponse(400, 'Unsupported grant_type.');
            return;
        }

        // GET request -> Token Verification
        $this->verifyToken();
    }

    /**
     * Method exchangeCodeForToken
     * @return void
     */
    private function exchangeCodeForToken(): void
    {
        $code = $_POST['code'] ?? '';
        $clientId = $_POST['client_id'] ?? '';
        $redirectUri = $_POST['redirect_uri'] ?? '';
        $codeVerifier = $_POST['code_verifier'] ?? '';

        $db = \Indieinabox\Database::getDb();
        $stmt = $db->prepare('SELECT * FROM indieauth_codes WHERE code_hash = :hash');
        $stmt->bindValue(':hash', hash('sha256', $code), \PDO::PARAM_STR);
        $stmt->execute();
        $codeData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$codeData) {
            $this->sendResponse(400, 'Invalid or expired authorization code.');
            return;
        }

        // Cleanup code
        $delStmt = $db->prepare('DELETE FROM indieauth_codes WHERE code_hash = :hash');
        $delStmt->bindValue(':hash', hash('sha256', $code), \PDO::PARAM_STR);
        $delStmt->execute();

        if ($codeData['expires_at'] < time()) {
            $this->sendResponse(400, 'Authorization code has expired.');
            return;
        }

        if (rtrim($codeData['client_id'], '/') !== rtrim($clientId, '/')) {
            $this->sendResponse(400, 'Client ID mismatch.');
            return;
        }

        if (rtrim($codeData['redirect_uri'], '/') !== rtrim($redirectUri, '/')) {
            $this->sendResponse(400, 'Redirect URI mismatch.');
            return;
        }

        // Verify PKCE
        if (!empty($codeData['code_challenge'])) {
            if (empty($codeVerifier)) {
                $this->sendResponse(400, 'Missing code_verifier for PKCE validation.');
                return;
            }

            $method = strtolower($codeData['code_challenge_method'] ?: 'plain');
            if ($method === 's256') {
                $challengeCalculated = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash('sha256', $codeVerifier, true)));
            } else {
                $challengeCalculated = $codeVerifier;
            }

            if (!hash_equals($codeData['code_challenge'], $challengeCalculated)) {
                $this->sendResponse(400, 'PKCE verification failed.');
                return;
            }
        }

        // Generate Access Token
        $token = 'ia_' . bin2hex(random_bytes(24));

        $insStmt = $db->prepare('INSERT INTO indieauth_tokens (token_hash, client_id, scope, me, created_at) VALUES (:hash, :client_id, :scope, :me, :created)');
        $insStmt->bindValue(':hash', hash('sha256', $token), \PDO::PARAM_STR);
        $insStmt->bindValue(':client_id', $clientId, \PDO::PARAM_STR);
        $insStmt->bindValue(':scope', $codeData['scope'], \PDO::PARAM_STR);
        $insStmt->bindValue(':me', $codeData['me'], \PDO::PARAM_STR);
        $insStmt->bindValue(':created', time(), \PDO::PARAM_INT);
        $insStmt->execute();

        header('HTTP/1.1 200 OK');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'access_token' => $token,
            'me' => $codeData['me'],
            'scope' => $codeData['scope'],
            'token_type' => 'Bearer'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Method validateBearerToken
     * @param ?string $tokenOut
     * 
     * @return ?array
     */
    public function validateBearerToken(?string &$tokenOut = null): ?array
    {
        $token = '';

        // Check Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = trim($matches[1]);
        } elseif (isset($_GET['access_token'])) {
            $token = $_GET['access_token'];
        } elseif (isset($_POST['access_token'])) {
            $token = $_POST['access_token'];
        }

        if (empty($token)) {
            return null;
        }

        if ($tokenOut !== null) {
            $tokenOut = $token;
        }

        $db = \Indieinabox\Database::getDb();
        $stmt = $db->prepare('SELECT * FROM indieauth_tokens WHERE token_hash = :hash');
        $stmt->bindValue(':hash', hash('sha256', $token), \PDO::PARAM_STR);
        $stmt->execute();
        $tokenData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tokenData) {
            return null;
        }

        return [
            'me' => $tokenData['me'],
            'client_id' => $tokenData['client_id'],
            'scope' => $tokenData['scope']
        ];
    }

    /**
     * Method verifyToken
     * @return void
     */
    private function verifyToken(): void
    {
        $tokenOut = null;
        $tokenData = $this->validateBearerToken($tokenOut);

        if ($tokenData === null) {
            $this->sendResponse(401, 'Unauthorized. Invalid or missing access token.');
            return;
        }

        header('HTTP/1.1 200 OK');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'me' => $tokenData['me'],
            'client_id' => $tokenData['client_id'],
            'scope' => $tokenData['scope']
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Method sendResponse
     * @param int $code
     * @param string $message
     * 
     * @return void
     */
    private function sendResponse(int $code, string $message): void
    {
        header('HTTP/1.1 ' . $code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $code,
            'message' => $message
        ], JSON_PRETTY_PRINT);
    }
}
