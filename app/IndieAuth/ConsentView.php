<?php

declare(strict_types=1);

namespace Indieinabox\IndieAuth;

use Indieinabox\Site\Site;

/**
 * Class ConsentView
 *
 * Renders the HTML consent/authorization screen and JSON error responses for IndieAuth.
 */
class ConsentView
{
    /**
     * Renders the HTML login and authorization consent form.
     *
     * @param Site $site Site metadata.
     * @param array<string, string> $params Authorization request parameters.
     * @param string|null $error Optional error message.
     * @return void
     */
    public static function renderLoginForm(Site $site, array $params, ?string $error = null): void
    {
        $clientId = $params['client_id'] ?? '';
        $redirectUri = $params['redirect_uri'] ?? '';
        $state = $params['state'] ?? '';
        $scope = $params['scope'] ?? '';
        $codeChallenge = $params['code_challenge'] ?? '';
        $codeChallengeMethod = $params['code_challenge_method'] ?? '';

        if (empty($clientId) || empty($redirectUri)) {
            self::renderError(400, 'Missing client_id or redirect_uri parameters.');
            return;
        }

        $fqdn = rtrim($site->metadata->fqdn ?? '', '/');

        http_response_code(200);
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
     * Sends a JSON error response.
     *
     * @param int $code HTTP status code.
     * @param string $message Response message.
     * @return void
     */
    public static function renderError(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $code,
            'message' => $message,
        ], JSON_PRETTY_PRINT);
    }
}
