<?php

declare(strict_types=1);

namespace Indieinabox;

class ConfigHandler
{
    private Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    public function handle(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $requestUriClean = rtrim($requestUri, '/');

        // Check if configuration exists
        $basePath = $this->site->paths->baseDir;
        $configFile = $basePath . DIRECTORY_SEPARATOR . "config.yml";
        if (file_exists($basePath . DIRECTORY_SEPARATOR . ".config.yml")) {
            $configFile = $basePath . DIRECTORY_SEPARATOR . ".config.yml";
        }

        $hasPassword = !empty($this->site->metadata->indieauthPassword);

        // Bootstrap flow: No config file or password exists yet
        if (!file_exists($configFile) || !$hasPassword) {
            $this->handleBootstrap();
            return;
        }

        // Handle logout
        if (isset($_GET['action']) && $_GET['action'] === 'logout') {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            header('Location: ' . rtrim($this->site->metadata->fqdn, '/') . '/config');
            return;
        }

        // Handle IndieAuth callback verification
        if (isset($_GET['code']) && isset($_GET['state'])) {
            $this->handleCallback();
            return;
        }

        // Require authentication
        if (empty($_SESSION['admin_authenticated'])) {
            $this->redirectToAuth();
            return;
        }

        // Process POST updates
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->saveConfig();
            return;
        }

        $this->renderConfigForm();
    }

    private function handleBootstrap(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $password = $_POST['indieauth_password'] ?? '';
            $title = $_POST['title'] ?? 'My Site';
            $sitename = $_POST['sitename'] ?? 'My Site Name';
            $fqdn = $_POST['fqdn'] ?? '';

            if (empty($password)) {
                $this->renderBootstrapForm('Password cannot be empty.');
                return;
            }

            if (empty($fqdn)) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $fqdn = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080');
            }
            $fqdn = rtrim($fqdn, '/');

            // Build bootstrap config array
            $newConfig = [
                'base' => '/',
                'title' => $title,
                'sitename' => $sitename,
                'fqdn' => $fqdn,
                'author' => '~admin',
                'indieauth_password' => password_hash($password, PASSWORD_BCRYPT),
                'buildall' => true,
                'outputdir' => 'public',
                'contentdir' => 'content',
                'lang' => ['en'],
                'defaultlang' => 'en',
                'support' => ['md', 'txt', 'html', 'htm'],
                'htmlpostprocessing' => 'minify',
                'prettylinks' => $this->detectPrettyLinksSupport()
            ];

            $yaml = new Yaml();
            $yamlContent = $yaml->dump($newConfig);
            $basePath = $this->site->paths->baseDir;
            file_put_contents($basePath . DIRECTORY_SEPARATOR . '.config.yml', $yamlContent);

            // Rebuild site once initial configs are generated
            $this->rebuildSite();

            // Redirect to normal login endpoint
            header('Location: ' . $fqdn . '/config');
            return;
        }

        $this->renderBootstrapForm();
    }

    private function handleCallback(): void
    {
        $state = $_GET['state'] ?? '';
        $code = $_GET['code'] ?? '';

        if (empty($_SESSION['auth_state']) || !hash_equals($_SESSION['auth_state'], $state)) {
            $this->sendError(400, 'Invalid state parameter.');
            return;
        }

        $basePath = $this->site->paths->baseDir;
        $codesDir = $basePath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'indieauth' . DIRECTORY_SEPARATOR . 'codes';
        $codeFile = $codesDir . DIRECTORY_SEPARATOR . md5($code) . '.json';

        if (!file_exists($codeFile)) {
            $this->sendError(400, 'Invalid or expired authorization code.');
            return;
        }

        $codeData = json_decode(file_get_contents($codeFile), true);
        @unlink($codeFile);

        if ($codeData['expires_at'] < time()) {
            $this->sendError(400, 'Authorization code has expired.');
            return;
        }

        $expectedClientId = rtrim($this->site->metadata->fqdn, '/') . '/config';
        if (rtrim($codeData['client_id'], '/') !== rtrim($expectedClientId, '/')) {
            $this->sendError(400, 'Client ID mismatch.');
            return;
        }

        // Elevate session status
        $_SESSION['admin_authenticated'] = true;
        unset($_SESSION['auth_state']);

        header('Location: ' . rtrim($this->site->metadata->fqdn, '/') . '/config');
        return;
    }

    private function redirectToAuth(): void
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['auth_state'] = $state;

        $fqdn = rtrim($this->site->metadata->fqdn, '/');
        $clientId = $fqdn . '/config';
        $redirectUri = $fqdn . '/config';

        $authUrl = $fqdn . '/auth'
            . '?client_id=' . urlencode($clientId)
            . '&redirect_uri=' . urlencode($redirectUri)
            . '&state=' . urlencode($state)
            . '&scope=config'
            . '&response_type=code';

        header('Location: ' . $authUrl);
        return;
    }

    private function saveConfig(): void
    {
        $basePath = $this->site->paths->baseDir;
        $yaml = new Yaml();

        $configFile = $basePath . DIRECTORY_SEPARATOR . "config.yml";
        if (file_exists($basePath . DIRECTORY_SEPARATOR . ".config.yml")) {
            $configFile = $basePath . DIRECTORY_SEPARATOR . ".config.yml";
        }
        
        $currentConfig = [];
        if (file_exists($configFile)) {
            $currentConfig = $yaml->loadFile($configFile);
        }

        // Parse directories with defaults/fallbacks
        $contentDir = !empty($_POST['contentdir']) ? trim($_POST['contentdir']) : 'content';
        $outputDir = !empty($_POST['outputdir']) ? trim($_POST['outputdir']) : 'public';

        // Format base path
        $base = trim($_POST['base'] ?? '', '/');
        if (strlen($base) > 0) {
            $base = '/' . $base;
        } else {
            $base = '/';
        }

        // Support extensions array
        $supportVal = $_POST['support'] ?? 'md, txt, html, htm';
        $support = array_map('trim', explode(',', $supportVal));

        // Languages array
        $langVal = $_POST['lang'] ?? 'en';
        $lang = array_map('trim', explode(',', $langVal));

        // Update core config values
        $currentConfig['base'] = $base;
        $currentConfig['title'] = trim($_POST['title'] ?? 'My Site');
        $currentConfig['sitename'] = trim($_POST['sitename'] ?? 'My Site Name');
        $currentConfig['fqdn'] = rtrim(trim($_POST['fqdn'] ?? ''), '/');
        $currentConfig['author'] = trim($_POST['author'] ?? '');
        $currentConfig['contentdir'] = $contentDir;
        $currentConfig['outputdir'] = $outputDir;
        $currentConfig['defaultcategory'] = trim($_POST['defaultcategory'] ?? 'General');
        $currentConfig['htmlpostprocessing'] = $_POST['htmlpostprocessing'] ?? 'minify';
        $currentConfig['buildall'] = isset($_POST['buildall']);
        $currentConfig['dev'] = isset($_POST['dev']);
        $currentConfig['prettylinks'] = isset($_POST['prettylinks']);
        $currentConfig['support'] = $support;
        $currentConfig['lang'] = $lang;

        // Save Twtxt configs
        $twtxtNick = trim($_POST['twtxt_nick'] ?? '');
        $twtxtDesc = trim($_POST['twtxt_description'] ?? '');
        $twtxtAvatar = trim($_POST['twtxt_avatar'] ?? '');

        // Subscriptions
        $following = [];
        $followText = $_POST['twtxt_following'] ?? '';
        $lines = explode("\n", $followText);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $line, 2);
            if (count($parts) === 2) {
                $following[] = [
                    'nick' => $parts[0],
                    'url' => $parts[1]
                ];
            }
        }

        // Hubs
        $hubs = [];
        $hubsText = $_POST['twtxt_hubs'] ?? '';
        $lines = explode("\n", $hubsText);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $hubs[] = $line;
            }
        }

        $currentConfig['twtxt'] = [
            'nick' => $twtxtNick,
            'description' => $twtxtDesc,
            'avatar' => $twtxtAvatar,
            'following' => $following,
            'hubs' => $hubs
        ];

        // Process security password change
        if (!empty($_POST['new_password'])) {
            $currentConfig['indieauth_password'] = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
        }

        // Save to .config.yml to protect secrets
        $yamlContent = $yaml->dump($currentConfig);
        file_put_contents($basePath . DIRECTORY_SEPARATOR . '.config.yml', $yamlContent);

        // Rebuild the site using newly saved settings
        $this->rebuildSite();

        header('Location: ' . rtrim($currentConfig['fqdn'], '/') . '/config?saved=1');
        return;
    }

    private function rebuildSite(): void
    {
        $basePath = $this->site->paths->baseDir;
        $yaml = new Yaml();

        $configFile = $basePath . DIRECTORY_SEPARATOR . ".config.yml";
        if (!file_exists($configFile)) {
            $configFile = $basePath . DIRECTORY_SEPARATOR . "config.yml";
        }

        if (!file_exists($configFile)) {
            return;
        }

        $config = $yaml->loadFile($configFile);

        $newSite = new Site();
        $newSite->paths->baseDir = $basePath;

        if (isset($config['title'])) {
            $newSite->metadata->title = $config['title'];
        }
        if (isset($config['sitename'])) {
            $newSite->metadata->sitename = $config['sitename'];
        }
        if (isset($config['author'])) {
            $newSite->metadata->author = $config['author'];
        }
        if (isset($config['fqdn'])) {
            $newSite->metadata->fqdn = $config['fqdn'];
        }
        if (isset($config['indieauth_password'])) {
            $newSite->metadata->indieauthPassword = (string)$config['indieauth_password'];
        }
        if (isset($config['support'])) {
            $newSite->support->support = $config['support'];
        }
        if (isset($config['buildall'])) {
            $newSite->options->buildAll = (bool)$config['buildall'];
        }
        if (isset($config['outputdir'])) {
            $newSite->paths->outputDir = $config['outputdir'];
        }
        if (isset($config['contentdir'])) {
            $newSite->paths->contentDir = $config['contentdir'];
        }
        if (isset($config['defaultcategory'])) {
            $newSite->support->defaultCategory = $config['defaultcategory'];
        }
        if (isset($config['htmlpostprocessing'])) {
            $newSite->options->htmlpostprocessing = $config['htmlpostprocessing'];
        }
        if (isset($config['dev'])) {
            $newSite->options->dev = (bool)$config['dev'];
        }
        if (isset($config['prettylinks'])) {
            $newSite->options->prettylinks = (bool)$config['prettylinks'];
        }

        if (isset($config['lang'])) {
            $newSite->localization->lang = $config['lang'];
            if (is_array($config['lang'])) {
                $newSite->localization->defaultLang = $config['lang'][0];
            } else {
                $newSite->localization->defaultLang = $config['lang'];
            }
        }

        if (isset($config['twtxt'])) {
            $twtxtData = $config['twtxt'];
            $newSite->twtxt->nick = (string) ($twtxtData['nick'] ?? '');
            $newSite->twtxt->description = (string) ($twtxtData['description'] ?? '');
            $newSite->twtxt->avatar = (string) ($twtxtData['avatar'] ?? '');
            $newSite->twtxt->following = (array) ($twtxtData['following'] ?? []);
            $newSite->twtxt->hubs = (array) ($twtxtData['hubs'] ?? []);
        }

        // Rebuild!
        $builder = new \Indieinabox\SiteBuilder($newSite);
        $builder->build();
        
        // Also update local site reference in handler
        $this->site = $newSite;
    }

    private function detectPrettyLinksSupport(): bool
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptFilename = basename($scriptName);
        if ($scriptFilename !== '' && strpos($requestUri, $scriptFilename) !== false) {
            return false;
        }
        return true;
    }

    private function renderBootstrapForm(?string $error = null): void
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $detectedFqdn = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080');

        header('HTTP/1.1 200 OK');
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Bootstrap Setup - Indieinabox</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
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
                    max-width: 500px;
                    width: 100%;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7),
                                0 0 50px rgba(236, 203, 0, 0.03);
                    position: relative;
                    overflow: hidden;
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
                    font-size: 0.95rem;
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

                form {
                    display: flex;
                    flex-direction: column;
                    gap: 1.25rem;
                }

                .form-group {
                    display: flex;
                    flex-direction: column;
                    gap: 0.4rem;
                }

                label {
                    font-weight: 600;
                    font-size: 0.85rem;
                    color: var(--text-secondary);
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }

                input {
                    background: var(--input-bg);
                    border: 1px solid var(--border);
                    border-radius: 12px;
                    padding: 0.85rem 1rem;
                    font-size: 1rem;
                    color: var(--text-primary);
                    transition: all 0.2s ease;
                }

                input:focus {
                    outline: none;
                    border-color: var(--accent);
                    box-shadow: 0 0 0 4px var(--input-focus);
                    background: rgba(3, 7, 18, 0.8);
                }

                button {
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
                    margin-top: 1rem;
                }

                button:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 6px 20px var(--accent-glow);
                    background: linear-gradient(135deg, #fce029 0%, #eccb00 100%);
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Setup Setup Setup!</h1>
                <p class="subtitle">Indieinabox is not configured yet. Choose your password and site identity to get started.</p>

                <?php if ($error): ?>
                    <div class="error-message"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="form-group">
                        <label for="indieauth_password">IndieAuth Password</label>
                        <input type="password" name="indieauth_password" id="indieauth_password" required placeholder="••••••••" autofocus>
                    </div>

                    <div class="form-group">
                        <label for="title">Site Title</label>
                        <input type="text" name="title" id="title" value="Lumen Pink" required>
                    </div>

                    <div class="form-group">
                        <label for="sitename">Site Name</label>
                        <input type="text" name="sitename" id="sitename" value="A nova rede social da Lumen" required>
                    </div>

                    <div class="form-group">
                        <label for="fqdn">Site FQDN (URL)</label>
                        <input type="url" name="fqdn" id="fqdn" value="<?= htmlspecialchars($detectedFqdn) ?>" required>
                    </div>

                    <button type="submit">Configure & Rebuild</button>
                </form>
            </div>
        </body>
        </html>
        <?php
    }

    private function renderConfigForm(): void
    {
        $basePath = $this->site->paths->baseDir;
        $yaml = new Yaml();

        $configFile = $basePath . DIRECTORY_SEPARATOR . ".config.yml";
        if (!file_exists($configFile)) {
            $configFile = $basePath . DIRECTORY_SEPARATOR . "config.yml";
        }

        $config = [];
        if (file_exists($configFile)) {
            $config = $yaml->loadFile($configFile);
        }

        $prettyLinksActive = $config['prettylinks'] ?? $this->detectPrettyLinksSupport();

        header('HTTP/1.1 200 OK');
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Web Settings - Indieinabox</title>
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
                    --tab-inactive: rgba(255, 255, 255, 0.04);
                    --tab-hover: rgba(255, 255, 255, 0.08);
                }

                body {
                    font-family: 'Outfit', sans-serif;
                    background: var(--bg-gradient);
                    background-attachment: fixed;
                    color: var(--text-primary);
                    margin: 0;
                    padding: 2rem 1.5rem;
                    box-sizing: border-box;
                    min-height: 100vh;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                }

                .nav-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    width: 100%;
                    max-width: 800px;
                    margin-bottom: 2rem;
                }

                .nav-header h1 {
                    margin: 0;
                    font-size: 1.8rem;
                    font-weight: 800;
                    background: linear-gradient(90deg, #ffffff, #eccb00);
                    -webkit-background-clip: text;
                    background-clip: text;
                    -webkit-text-fill-color: transparent;
                }

                .logout-btn {
                    text-decoration: none;
                    font-size: 0.9rem;
                    font-weight: 600;
                    color: var(--text-secondary);
                    border: 1px solid var(--border);
                    padding: 0.5rem 1rem;
                    border-radius: 8px;
                    transition: all 0.2s ease;
                    background: rgba(255, 255, 255, 0.02);
                }

                .logout-btn:hover {
                    color: var(--text-primary);
                    background: rgba(239, 68, 68, 0.15);
                    border-color: rgba(239, 68, 68, 0.3);
                }

                .main-card {
                    backdrop-filter: blur(20px);
                    -webkit-backdrop-filter: blur(20px);
                    background: var(--card-bg);
                    border: 1px solid var(--border);
                    border-radius: 24px;
                    width: 100%;
                    max-width: 800px;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
                    overflow: hidden;
                    position: relative;
                }

                .main-card::before {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    height: 4px;
                    background: linear-gradient(90deg, #eccb00, #f59e0b);
                }

                .tabs-header {
                    display: flex;
                    border-bottom: 1px solid var(--border);
                    background: rgba(0, 0, 0, 0.2);
                }

                .tab-btn {
                    flex: 1;
                    background: none;
                    border: none;
                    padding: 1.25rem 1rem;
                    font-family: inherit;
                    font-size: 0.95rem;
                    font-weight: 600;
                    color: var(--text-secondary);
                    cursor: pointer;
                    transition: all 0.2s ease;
                    border-bottom: 2px solid transparent;
                    text-align: center;
                }

                .tab-btn:hover {
                    color: var(--text-primary);
                    background: var(--tab-hover);
                }

                .tab-btn.active {
                    color: var(--accent);
                    border-bottom-color: var(--accent);
                    background: rgba(236, 203, 0, 0.03);
                }

                .tab-content {
                    display: none;
                    padding: 2.5rem;
                }

                .tab-content.active {
                    display: block;
                }

                .form-section {
                    display: flex;
                    flex-direction: column;
                    gap: 1.5rem;
                }

                .form-group {
                    display: flex;
                    flex-direction: column;
                    gap: 0.5rem;
                }

                .form-row {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 1.5rem;
                }

                label {
                    font-weight: 600;
                    font-size: 0.8rem;
                    color: var(--text-secondary);
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }

                .desc {
                    font-size: 0.85rem;
                    color: var(--text-secondary);
                    margin-top: -0.25rem;
                    margin-bottom: 0.25rem;
                }

                input[type="text"],
                input[type="url"],
                input[type="password"],
                select,
                textarea {
                    font-family: inherit;
                    background: var(--input-bg);
                    border: 1px solid var(--border);
                    border-radius: 12px;
                    padding: 0.85rem 1rem;
                    font-size: 1rem;
                    color: var(--text-primary);
                    transition: all 0.2s ease;
                    width: 100%;
                    box-sizing: border-box;
                }

                input[type="text"]:focus,
                input[type="url"]:focus,
                input[type="password"]:focus,
                select:focus,
                textarea:focus {
                    outline: none;
                    border-color: var(--accent);
                    box-shadow: 0 0 0 4px var(--input-focus);
                    background: rgba(3, 7, 18, 0.8);
                }

                textarea {
                    resize: vertical;
                    min-height: 120px;
                    font-family: 'JetBrains Mono', monospace;
                    font-size: 0.9rem;
                }

                .checkbox-group {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    margin-top: 0.5rem;
                    cursor: pointer;
                }

                .checkbox-group input[type="checkbox"] {
                    width: 20px;
                    height: 20px;
                    accent-color: var(--accent);
                    cursor: pointer;
                }

                .checkbox-label {
                    font-weight: 600;
                    font-size: 0.95rem;
                    color: var(--text-primary);
                    user-select: none;
                }

                .alert-saved {
                    background: rgba(16, 185, 129, 0.1);
                    border: 1px solid rgba(16, 185, 129, 0.2);
                    border-radius: 12px;
                    padding: 1rem;
                    color: #10b981;
                    font-size: 0.95rem;
                    font-weight: 600;
                    margin-bottom: 1.5rem;
                    width: 100%;
                    max-width: 800px;
                    box-sizing: border-box;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }

                .footer-bar {
                    display: flex;
                    justify-content: flex-end;
                    padding: 1.5rem 2.5rem;
                    background: rgba(0, 0, 0, 0.2);
                    border-top: 1px solid var(--border);
                }

                .save-btn {
                    background: linear-gradient(135deg, #eccb00 0%, #d8b600 100%);
                    color: #030712;
                    border: none;
                    padding: 0.9rem 2rem;
                    border-radius: 12px;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                    box-shadow: 0 4px 12px var(--accent-glow);
                }

                .save-btn:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 6px 20px var(--accent-glow);
                    background: linear-gradient(135deg, #fce029 0%, #eccb00 100%);
                }

                @media (max-width: 600px) {
                    .form-row {
                        grid-template-columns: 1fr;
                    }
                    .tabs-header {
                        flex-wrap: wrap;
                    }
                    .tab-btn {
                        flex: unset;
                        width: 50%;
                    }
                }
            </style>
        </head>
        <body>
            <div class="nav-header">
                <h1>Configuration Panel</h1>
                <a href="?action=logout" class="logout-btn">Log Out</a>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div class="alert-saved">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    Settings saved successfully! Site has been automatically rebuilt.
                </div>
            <?php endif; ?>

            <div class="main-card">
                <div class="tabs-header">
                    <button class="tab-btn active" onclick="switchTab(event, 'general')">🌐 General</button>
                    <button class="tab-btn" onclick="switchTab(event, 'build')">⚙️ Build</button>
                    <button class="tab-btn" onclick="switchTab(event, 'twtxt')">📡 Twtxt</button>
                    <button class="tab-btn" onclick="switchTab(event, 'security')">🔒 Security</button>
                </div>

                <form action="" method="POST" id="configForm">
                    <!-- Tab: General -->
                    <div id="general" class="tab-content active">
                        <div class="form-section">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="title">Site Title</label>
                                    <input type="text" name="title" id="title" value="<?= htmlspecialchars($config['title'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="sitename">Site Name</label>
                                    <input type="text" name="sitename" id="sitename" value="<?= htmlspecialchars($config['sitename'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="author">Author Name</label>
                                    <input type="text" name="author" id="author" value="<?= htmlspecialchars($config['author'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="fqdn">Site FQDN (URL)</label>
                                    <input type="url" name="fqdn" id="fqdn" value="<?= htmlspecialchars($config['fqdn'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="base">Base Path</label>
                                <p class="desc">Subdirectory path if not hosted at the domain root (e.g. <code>/blog</code>). Use <code>/</code> for root.</p>
                                <input type="text" name="base" id="base" value="<?= htmlspecialchars($config['base'] ?? '/') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Build -->
                    <div id="build" class="tab-content">
                        <div class="form-section">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="contentdir">Content Directory</label>
                                    <p class="desc">Where source posts and notes reside (default: <code>content</code>).</p>
                                    <input type="text" name="contentdir" id="contentdir" value="<?= htmlspecialchars($config['contentdir'] ?? 'content') ?>" placeholder="content">
                                </div>
                                <div class="form-group">
                                    <label for="outputdir">Publish Directory</label>
                                    <p class="desc">Where static HTML outputs are built (default: <code>public</code>).</p>
                                    <input type="text" name="outputdir" id="outputdir" value="<?= htmlspecialchars($config['outputdir'] ?? 'public') ?>" placeholder="public">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="defaultcategory">Default Category</label>
                                    <input type="text" name="defaultcategory" id="defaultcategory" value="<?= htmlspecialchars($config['defaultcategory'] ?? 'General') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="htmlpostprocessing">HTML Postprocessing</label>
                                    <select name="htmlpostprocessing" id="htmlpostprocessing">
                                        <option value="none" <?= ($config['htmlpostprocessing'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                        <option value="minify" <?= ($config['htmlpostprocessing'] ?? 'minify') === 'minify' ? 'selected' : '' ?>>Minify</option>
                                        <option value="beautify" <?= ($config['htmlpostprocessing'] ?? '') === 'beautify' ? 'selected' : '' ?>>Beautify</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="support">Supported File Extensions</label>
                                <p class="desc">Comma-separated list of formats parsed by the builder.</p>
                                <input type="text" name="support" id="support" value="<?= htmlspecialchars(implode(', ', $config['support'] ?? ['md', 'txt', 'html', 'htm'])) ?>">
                            </div>

                            <div class="form-group">
                                <label for="lang">Supported Languages</label>
                                <p class="desc">Comma-separated languages (first one is the default).</p>
                                <input type="text" name="lang" id="lang" value="<?= htmlspecialchars(implode(', ', (array)($config['lang'] ?? 'en'))) ?>">
                            </div>

                            <div class="form-group">
                                <label class="checkbox-group">
                                    <input type="checkbox" name="prettylinks" id="prettylinks" <?= $prettyLinksActive ? 'checked' : '' ?>>
                                    <span class="checkbox-label">Pretty Links (use <code>folder/index.html</code> format)</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-group">
                                    <input type="checkbox" name="buildall" id="buildall" <?= ($config['buildall'] ?? true) ? 'checked' : '' ?>>
                                    <span class="checkbox-label">Build pages without frontmatter</span>
                                </label>
                            </div>

                            <div class="form-group">
                                <label class="checkbox-group">
                                    <input type="checkbox" name="dev" id="dev" <?= ($config['dev'] ?? false) ? 'checked' : '' ?>>
                                    <span class="checkbox-label">Dev mode (enables live-reload script)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Twtxt -->
                    <div id="twtxt" class="tab-content">
                        <div class="form-section">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="twtxt_nick">Twtxt Nickname</label>
                                    <input type="text" name="twtxt_nick" id="twtxt_nick" value="<?= htmlspecialchars($config['twtxt']['nick'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="twtxt_avatar">Twtxt Avatar URL</label>
                                    <input type="url" name="twtxt_avatar" id="twtxt_avatar" value="<?= htmlspecialchars($config['twtxt']['avatar'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="twtxt_description">Twtxt Description</label>
                                <input type="text" name="twtxt_description" id="twtxt_description" value="<?= htmlspecialchars($config['twtxt']['description'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="twtxt_following">Subscribed Feeds</label>
                                <p class="desc">Format: <code>nickname feed_url</code> (one per line).</p>
                                <?php
                                $followLines = [];
                                foreach (($config['twtxt']['following'] ?? []) as $f) {
                                    $followLines[] = "{$f['nick']} {$f['url']}";
                                }
                                ?>
                                <textarea name="twtxt_following" id="twtxt_following" placeholder="bob https://bob.com/twtxt.txt"><?= htmlspecialchars(implode("\n", $followLines)) ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="twtxt_hubs">Configured Hubs</label>
                                <p class="desc">List of Twtxt aggregation hubs (one URL per line).</p>
                                <textarea name="twtxt_hubs" id="twtxt_hubs" placeholder="https://hub.twtxt.org"><?= htmlspecialchars(implode("\n", $config['twtxt']['hubs'] ?? [])) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Tab: Security -->
                    <div id="security" class="tab-content">
                        <div class="form-section">
                            <div class="form-group">
                                <label for="new_password">New IndieAuth Password</label>
                                <p class="desc">Leave blank to keep the current administrative password.</p>
                                <input type="password" name="new_password" id="new_password" placeholder="••••••••">
                            </div>
                        </div>
                    </div>

                    <div class="footer-bar">
                        <button type="submit" class="save-btn">Rebuild & Save Settings</button>
                    </div>
                </form>
            </div>

            <script>
                function switchTab(evt, tabId) {
                    // Hide all tab content
                    const tabContents = document.getElementsByClassName('tab-content');
                    for (let i = 0; i < tabContents.length; i++) {
                        tabContents[i].classList.remove('active');
                    }

                    // Remove active class from all tab buttons
                    const tabButtons = document.getElementsByClassName('tab-btn');
                    for (let i = 0; i < tabButtons.length; i++) {
                        tabButtons[i].classList.remove('active');
                    }

                    // Show active tab, make button active
                    document.getElementById(tabId).classList.add('active');
                    evt.currentTarget.classList.add('active');
                }
            </script>
        </body>
        </html>
        <?php
    }

    private function sendError(int $code, string $message): void
    {
        header('HTTP/1.1 ' . $code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
    }
}
