<?php

declare(strict_types=1);

namespace Indieinabox;

/**
 * Class ConfigHandler
 */
class ConfigHandler
{
    /**
     * @var \Indieinabox\Site
     */
    private Site $site;

    /**
     * Initializes the ConfigHandler with the site context.
     *
     * @param \Indieinabox\Site $site Global site configuration and environment.
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Main handler entry point.
     * Manages bootstrapping, login, callbacks, configuration saving, and rendering 
     * the admin configuration panel based on the request method and session state.
     *
     * @return void
     */
    public function handle(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $requestUriClean = rtrim($requestUri, '/');

        $hasPassword = !empty($this->site->metadata->indieauthPassword);

        // Bootstrap flow: No password exists yet
        if (!$hasPassword) {
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
            header('Location: /admin/config');
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
            if (isset($_POST['action'])) {
                if ($_POST['action'] === 'manual_update' && !empty($_POST['download_url'])) {
                    $success = \Indieinabox\Updater::downloadAndInstall($_POST['download_url']);
                    header('Location: /admin/config?saved=1');
                    exit;
                }
                if ($_POST['action'] === 'rollback_update' && !empty($_POST['backup_filename'])) {
                    $success = \Indieinabox\Updater::rollback($_POST['backup_filename']);
                    header('Location: /admin/config?saved=1');
                    exit;
                }
                if ($_POST['action'] === 'rebuild_site') {
                    $this->rebuildSite();
                    header('Location: /admin/config?rebuilt=1');
                    exit;
                }
            }
            $this->saveConfig();
            return;
        }

        $this->renderConfigForm();
    }

    /**
     * Handles the initial bootstrapping of a new site installation.
     * Creates an admin user, hashes their password, and saves initial metadata.
     *
     * @return void
     */
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

            $db = \Indieinabox\Database::getDb();
            foreach ($newConfig as $key => $val) {
                $valStr = is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : (string)$val;
                $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :val)');
                $stmt->bindValue(':key', (string)$key);
                $stmt->bindValue(':val', $valStr);
                $stmt->execute();
            }

            // Rebuild site once initial configs are generated
            $this->rebuildSite();

            // Redirect to normal login endpoint
            header('Location: /admin/config');
            return;
        }

        $this->renderBootstrapForm();
    }

    /**
     * Processes the IndieAuth callback payload.
     * Verifies the OAuth state and authorization code with the authorization endpoint
     * to authenticate the admin user.
     *
     * @return void
     */
    private function handleCallback(): void
    {
        $state = $_GET['state'] ?? '';
        $code = $_GET['code'] ?? '';

        if (empty($_SESSION['auth_state']) || !hash_equals($_SESSION['auth_state'], $state)) {
            $this->sendError(400, 'Invalid state parameter.');
            return;
        }

        $db = \Indieinabox\Database::getDb();
        $codeHash = hash('sha256', $code);
        $stmt = $db->prepare('SELECT * FROM indieauth_codes WHERE code_hash = :hash');
        $stmt->bindValue(':hash', $codeHash);
        $stmt->execute();
        $codeData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$codeData) {
            $this->sendError(400, 'Invalid or expired authorization code.');
            return;
        }

        $stmtDel = $db->prepare('DELETE FROM indieauth_codes WHERE code_hash = :hash');
        $stmtDel->bindValue(':hash', $codeHash);
        $stmtDel->execute();

        if ($codeData['expires_at'] < time()) {
            $this->sendError(400, 'Authorization code has expired.');
            return;
        }

        $expectedClientId = rtrim($this->site->metadata->fqdn, '/') . '/admin/config';
        if (rtrim($codeData['client_id'], '/') !== rtrim($expectedClientId, '/')) {
            $this->sendError(400, 'Client ID mismatch.');
            return;
        }

        // Elevate session status
        $_SESSION['admin_authenticated'] = true;
        unset($_SESSION['auth_state']);

        header('Location: /admin/config');
        return;
    }

    /**
     * Redirects the user to the IndieAuth authorization endpoint.
     * Generates PKCE challenges, stores the state in the session, and initiates the OAuth flow.
     *
     * @return void
     */
    private function redirectToAuth(): void
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['auth_state'] = $state;

        $fqdn = rtrim($this->site->metadata->fqdn, '/');
        $clientId = $fqdn . '/admin/config';
        $redirectUri = $fqdn . '/admin/config';

        $authUrl = $fqdn . '/auth'
            . '?client_id=' . urlencode($clientId)
            . '&redirect_uri=' . urlencode($redirectUri)
            . '&state=' . urlencode($state)
            . '&scope=config'
            . '&response_type=code';

        header('Location: ' . $authUrl);
        return;
    }

    /**
     * Processes form submissions from the configuration panel.
     * Validates and saves metadata, options, themes, feeds, plugins, and kinds 
     * into the database. Also triggers a site rebuild upon saving.
     *
     * @return void
     */
    private function saveConfig(): void
    {
        $currentConfig = \Indieinabox\Database::getAllSettings();
        $currentConfig['kinds'] = \Indieinabox\Database::getKinds();
        $currentConfig['translations'] = \Indieinabox\Database::getTranslations();
        $currentConfig['urltranslations'] = \Indieinabox\Database::getUrlTranslations();

        // --- Core Settings ---
        $currentConfig['base'] = trim($_POST['base'] ?? '', '/');
        if (strlen($currentConfig['base']) > 0 && $currentConfig['base'] !== '/') {
            $currentConfig['base'] = '/' . ltrim($currentConfig['base'], '/');
        } else {
            $currentConfig['base'] = '/';
        }
        
        $currentConfig['title'] = trim($_POST['title'] ?? 'My Site');
        $currentConfig['sitename'] = trim($_POST['sitename'] ?? 'My Site Name');
        $currentConfig['fqdn'] = rtrim(trim($_POST['fqdn'] ?? ''), '/');
        $currentConfig['author'] = trim($_POST['author'] ?? '');
        $currentConfig['contentdir'] = !empty($_POST['contentdir']) ? trim($_POST['contentdir']) : 'content';
        $currentConfig['outputdir'] = !empty($_POST['outputdir']) ? trim($_POST['outputdir']) : 'public';
        $currentConfig['defaultcategory'] = trim($_POST['defaultcategory'] ?? 'General');
        $currentConfig['htmlpostprocessing'] = $_POST['htmlpostprocessing'] ?? 'minify';
        
        $currentConfig['translation_parity'] = $_POST['translation_parity'] ?? 'full';
        $currentConfig['translation_auto'] = $_POST['translation_auto'] ?? 'pseudo';
        $currentConfig['akismet_api_key'] = trim($_POST['akismet_api_key'] ?? '');
        
        $currentConfig['active_theme'] = trim($_POST['active_theme'] ?? 'default');
        
        // Handle Official Theme Install
        if (!empty($_POST['install_official_theme'])) {
            $this->installThemeFromUrl($_POST['install_official_theme']);
        }

        // Handle Custom Theme Upload
        if (!empty($_FILES['custom_theme_zip']['tmp_name'])) {
            $this->installThemeFromZip($_FILES['custom_theme_zip']['tmp_name']);
        }

        // --- Booleans ---
        $currentConfig['dev'] = isset($_POST['dev']);
        if ($currentConfig['dev']) {
            $liveJsFile = \Indieinabox\Database::$dataDir . DIRECTORY_SEPARATOR . 'live.js';
            if (!file_exists($liveJsFile)) {
                $content = @file_get_contents('https://raw.githubusercontent.com/MartinKool/livejs/master/live.js');
                if ($content !== false) {
                    file_put_contents($liveJsFile, $content);
                }
            }
        }
        $currentConfig['buildall'] = isset($_POST['buildall']);
        $currentConfig['prettylinks'] = isset($_POST['prettylinks']);
        $currentConfig['activitypub_enabled'] = isset($_POST['activitypub_enabled']);
        $currentConfig['webmention_enabled'] = isset($_POST['webmention_enabled']);
        $currentConfig['webarchive_enabled'] = isset($_POST['webarchive_enabled']);
        $currentConfig['disable_shortlinks'] = isset($_POST['disable_shortlinks']);
        $currentConfig['skipstatic'] = isset($_POST['skipstatic']);
        $currentConfig['forcestaticoverride'] = isset($_POST['forcestaticoverride']);
        $currentConfig['activitypub_enabled'] = isset($_POST['activitypub_enabled']);
        $currentConfig['auto_upgrade_stable'] = !empty($_POST['auto_upgrade_stable']);
        $currentConfig['auto_upgrade_nightly'] = !empty($_POST['auto_upgrade_nightly']);

        // --- ActivityPub ---
        $currentConfig['activitypub_handle'] = trim($_POST['activitypub_handle'] ?? 'schwartz');
        $currentConfig['feed_limit'] = isset($_POST['feed_limit']) ? (int)$_POST['feed_limit'] : 20;

        // --- Arrays ---
        $supportVal = $_POST['support'] ?? 'md, txt, html, htm';
        $currentConfig['support'] = array_filter(array_map('trim', explode(',', $supportVal)));
        
        $langs = [];
        if (isset($_POST['lang'])) {
            if (is_array($_POST['lang'])) {
                $langs = array_values(array_filter(array_map('trim', $_POST['lang'])));
            } else {
                $langs = array_values(array_filter(array_map('trim', explode(',', $_POST['lang']))));
            }
        }
        
        if (isset($_POST['remove_lang'])) {
            $removeLang = trim($_POST['remove_lang']);
            $langs = array_filter($langs, function ($l) use ($removeLang) {
                return $l !== $removeLang;
            });
            $langs = array_values($langs);
        }

        if (empty($langs)) {
            $langs = ['en'];
        }

        $oldLangs = $currentConfig['lang'] ?? ['en'];
        if (!is_array($oldLangs)) $oldLangs = [$oldLangs];
        $addedLangs = array_diff($langs, $oldLangs);
        $defaultOldLang = $oldLangs[0] ?? 'en';

        $currentConfig['lang'] = $langs;
        $currentConfig['defaultlang'] = $langs[0];

        // --- Twtxt ---
        $twtxtNick = trim($_POST['twtxt_nick'] ?? '');
        $twtxtDesc = trim($_POST['twtxt_description'] ?? '');
        $twtxtAvatar = trim($_POST['twtxt_avatar'] ?? '');

        $following = [];
        $followText = $_POST['twtxt_following'] ?? '';
        foreach (explode("\n", $followText) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = preg_split('/\s+/', $line, 2);
            if (count($parts) === 2) {
                $following[] = ['nick' => $parts[0], 'url' => $parts[1]];
            }
        }

        $hubs = [];
        $hubsText = $_POST['twtxt_hubs'] ?? '';
        foreach (explode("\n", $hubsText) as $line) {
            $line = trim($line);
            if ($line !== '') $hubs[] = $line;
        }

        $currentConfig['twtxt'] = [
            'nick' => $twtxtNick,
            'description' => $twtxtDesc,
            'avatar' => $twtxtAvatar,
            'following' => $following,
            'hubs' => $hubs
        ];

        // --- Shortlink ---
        if (isset($_POST['shortlink']) && is_array($_POST['shortlink'])) {
            $currentConfig['shortlink'] = [
                'enabled' => isset($_POST['shortlink']['enabled']),
                'server' => trim($_POST['shortlink']['server'] ?? ''),
                'parameter' => trim($_POST['shortlink']['parameter'] ?? 'shorten'),
                'auth_header' => trim($_POST['shortlink']['auth_header'] ?? ''),
                'auth_token' => trim($_POST['shortlink']['auth_token'] ?? '')
            ];
        }

        // --- Kinds ---
        $removeKind = isset($_POST['remove_kind']) ? trim($_POST['remove_kind']) : null;
        if (isset($_POST['kinds']) && is_array($_POST['kinds'])) {
            $newKinds = [];
            foreach ($_POST['kinds'] as $k => $data) {
                // Ignore empty __new__ row
                if ($k === '__new__') {
                    if (empty($data['content_dir']) || empty($data['key'])) {
                        continue;
                    }
                    $k = trim($data['key']);
                    if (empty($k)) continue;
                }
                
                // Process deletion via remove button
                if ($removeKind !== null && $k === $removeKind) {
                    continue;
                }

                $newKinds[$k] = [
                    'content_dir' => trim($data['content_dir'] ?? ''),
                    'title' => $data['title'] ?? [],
                    'palette' => [
                        'bg' => trim($data['palette']['bg'] ?? '#ffffff'),
                        'fg' => trim($data['palette']['fg'] ?? '#000000'),
                    ],
                    'has_title' => isset($data['has_title']),
                    'show_on_home' => isset($data['show_on_home']),
                    'show_in_menu' => isset($data['show_in_menu']),
                    'display_mode' => trim($data['display_mode'] ?? 'default'),
                ];
            }
            
            if (empty($newKinds)) {
                $defaultKindTitle = [];
                foreach ($langs as $l) {
                    $defaultKindTitle[$l] = 'Articles';
                }
                $newKinds['article'] = [
                    'content_dir' => 'articles',
                    'title' => $defaultKindTitle,
                    'palette' => [
                        'bg' => '#ffffff',
                        'fg' => '#000000',
                    ],
                    'has_title' => true,
                    'show_on_home' => true,
                    'show_in_menu' => true,
                    'display_mode' => 'default',
                ];
            }
            $currentConfig['kinds'] = $newKinds;
        }

        if (empty($currentConfig['kinds'])) {
            $defaultKindTitle = [];
            foreach ($langs as $l) {
                $defaultKindTitle[$l] = 'Articles';
            }
            $currentConfig['kinds'] = [
                'article' => [
                    'content_dir' => 'articles',
                    'title' => $defaultKindTitle,
                    'palette' => [
                        'bg' => '#ffffff',
                        'fg' => '#000000',
                    ],
                    'has_title' => true,
                    'show_on_home' => true,
                    'show_in_menu' => true,
                    'display_mode' => 'default',
                ]
            ];
        }

        // --- Translations ---
        if (isset($_POST['translations']) && is_array($_POST['translations'])) {
            $newTranslations = [];
            foreach ($_POST['translations'] as $origText => $langsData) {
                if (is_array($langsData)) {
                    foreach ($langsData as $langCode => $val) {
                        $newTranslations[trim($origText)][trim($langCode)] = trim($val);
                    }
                }
            }
            $currentConfig['translations'] = $newTranslations;
        }

        // --- URL Translations ---
        if (isset($_POST['urltranslations']) && is_array($_POST['urltranslations'])) {
            $newUrlTranslations = [];
            foreach ($_POST['urltranslations'] as $origUrl => $langsData) {
                if (is_array($langsData)) {
                    foreach ($langsData as $langCode => $val) {
                        $newUrlTranslations[trim($origUrl)][trim($langCode)] = trim($val);
                    }
                }
            }
            $currentConfig['urltranslations'] = $newUrlTranslations;
        }

        // --- Auto-fill Translations for new languages ---
        if (!empty($addedLangs)) {
            if (!empty($currentConfig['translations'])) {
                foreach ($currentConfig['translations'] as $phraseKey => &$langVals) {
                    foreach ($addedLangs as $nl) {
                        if (!isset($langVals[$nl]) || $langVals[$nl] === '') {
                            $langVals[$nl] = $langVals[$defaultOldLang] ?? $phraseKey;
                        }
                    }
                }
                unset($langVals);
            }
            if (!empty($currentConfig['urltranslations'])) {
                foreach ($currentConfig['urltranslations'] as $urlKey => &$langVals) {
                    foreach ($addedLangs as $nl) {
                        if (!isset($langVals[$nl]) || $langVals[$nl] === '') {
                            $langVals[$nl] = $langVals[$defaultOldLang] ?? $urlKey;
                        }
                    }
                }
                unset($langVals);
            }
            if (!empty($currentConfig['kinds'])) {
                foreach ($currentConfig['kinds'] as $k => &$kindData) {
                    if (isset($kindData['title']) && is_array($kindData['title'])) {
                        foreach ($addedLangs as $nl) {
                            if (!isset($kindData['title'][$nl]) || $kindData['title'][$nl] === '') {
                                $kindData['title'][$nl] = $kindData['title'][$defaultOldLang] ?? ucfirst($k);
                            }
                        }
                    }
                }
                unset($kindData);
            }
        }

        // --- Security ---
        if (!empty($_POST['new_password'])) {
            $currentConfig['indieauth_password'] = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
        }

        // Save to SQLite
        $db = \Indieinabox\Database::getDb();
        foreach ($currentConfig as $key => $val) {
            if ($key === 'kinds' || $key === 'translations' || $key === 'urltranslations') {
                continue;
            }
            $valStr = is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : (string)$val;
            $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :val)');
            $stmt->bindValue(':key', (string)$key);
            $stmt->bindValue(':val', $valStr);
            $stmt->execute();
        }

        $db->exec('DELETE FROM kinds');
        if (isset($currentConfig['kinds']) && is_array($currentConfig['kinds'])) {
            foreach ($currentConfig['kinds'] as $k => $v) {
                $valStr = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v;
                $stmt = $db->prepare('INSERT INTO kinds (kind_key, config_json) VALUES (:k, :v)');
                $stmt->bindValue(':k', (string)$k);
                $stmt->bindValue(':v', $valStr);
                $stmt->execute();
            }
        }

        if (isset($currentConfig['translations']) && is_array($currentConfig['translations'])) {
            foreach ($currentConfig['translations'] as $phrase_key => $langs) {
                if (is_array($langs)) {
                    foreach ($langs as $lang => $phrase_value) {
                        $stmt = $db->prepare('SELECT id FROM translations WHERE lang = :lang AND phrase_key = :key');
                        $stmt->bindValue(':lang', $lang);
                        $stmt->bindValue(':key', $phrase_key);
                        $stmt->execute();
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($row) {
                            $upd = $db->prepare('UPDATE translations SET phrase_value = :val WHERE id = :id');
                            $upd->bindValue(':val', $phrase_value);
                            $upd->bindValue(':id', $row['id']);
                            $upd->execute();
                        } else {
                            $ins = $db->prepare('INSERT INTO translations (lang, phrase_key, phrase_value) VALUES (:lang, :key, :val)');
                            $ins->bindValue(':lang', $lang);
                            $ins->bindValue(':key', $phrase_key);
                            $ins->bindValue(':val', $phrase_value);
                            $ins->execute();
                        }
                    }
                }
            }
        }

        if (isset($currentConfig['urltranslations']) && is_array($currentConfig['urltranslations'])) {
            foreach ($currentConfig['urltranslations'] as $url_key => $langs) {
                if (is_array($langs)) {
                    foreach ($langs as $lang => $url_value) {
                        $stmt = $db->prepare('SELECT id FROM url_translations WHERE lang = :lang AND slug_key = :key');
                        $stmt->bindValue(':lang', $lang);
                        $stmt->bindValue(':key', $url_key);
                        $stmt->execute();
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($row) {
                            $upd = $db->prepare('UPDATE url_translations SET slug_value = :val WHERE id = :id');
                            $upd->bindValue(':val', $url_value);
                            $upd->bindValue(':id', $row['id']);
                            $upd->execute();
                        } else {
                            $ins = $db->prepare('INSERT INTO url_translations (lang, slug_key, slug_value) VALUES (:lang, :key, :val)');
                            $ins->bindValue(':lang', $lang);
                            $ins->bindValue(':key', $url_key);
                            $ins->bindValue(':val', $url_value);
                            $ins->execute();
                        }
                    }
                }
            }
        }

        // Rebuild the site using newly saved settings
        $this->rebuildSite();

        $fqdn = rtrim($currentConfig['fqdn'] ?? '', '/');
        if (empty($fqdn)) {
            $fqdn = '';
        }
        header('Location: /admin/config?saved=1');
        return;
    }

    private function recursiveDeleteDir(string $dir): bool
    {
        if (!is_dir($dir)) return true;
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            is_dir("$dir/$file") ? $this->recursiveDeleteDir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    private function installThemeFromUrl(string $url): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'theme_');
        $content = @file_get_contents($url);
        if ($content !== false) {
            file_put_contents($tmpFile, $content);
            $this->installThemeFromZip($tmpFile);
        }
        @unlink($tmpFile);
    }

    private function installThemeFromZip(string $zipPath): void
    {
        if (!class_exists('\ZipArchive')) {
            // Cannot extract zip without ZipArchive extension
            return;
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) === true) {
            $tmpExtr = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('theme_extr_', true);
            mkdir($tmpExtr);
            $zip->extractTo($tmpExtr);
            $zip->close();
            
            $items = scandir($tmpExtr) ?: [];
            $themeDirName = null;
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..' && is_dir($tmpExtr . DIRECTORY_SEPARATOR . $item)) {
                    $themeDirName = $item;
                    break;
                }
            }
            
            if ($themeDirName) {
                $themesPath = \Indieinabox\Database::$dataDir . DIRECTORY_SEPARATOR . 'themes';
                if (!is_dir($themesPath)) {
                    mkdir($themesPath, 0777, true);
                }
                $targetDir = $themesPath . DIRECTORY_SEPARATOR . $themeDirName;
                if (is_dir($targetDir)) {
                    $this->recursiveDeleteDir($targetDir);
                }
                rename($tmpExtr . DIRECTORY_SEPARATOR . $themeDirName, $targetDir);
            }
            $this->recursiveDeleteDir($tmpExtr);
        }
    }

    /**
     * Triggers a site rebuild in the background by calling the CLI build script.
     * Returns early while the build process runs asynchronously.
     *
     * @return void
     */
    private function rebuildSite(): void
    {
        ob_start();
        $basePath = $this->site->paths->baseDir;
        $config = \Indieinabox\Database::getAllSettings();
        $config['kinds'] = \Indieinabox\Database::getKinds();
        $config['translations'] = \Indieinabox\Database::getTranslations();
        $config['urltranslations'] = \Indieinabox\Database::getUrlTranslations();

        $newSite = new Site();
        $newSite->paths->baseDir = $basePath;
        $newSite->config = $config;

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
            $baseOut = $config['outputdir'];
            $newSite->paths->outputDirHtml = $baseOut . '_html';
            $newSite->paths->outputDirGemini = $baseOut . '_gemini';
            $newSite->paths->outputDirGopher = $baseOut . '_gopher';
            $newSite->paths->outputDirMedia = $baseOut . '_media';
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
        if (isset($config['feed_limit'])) {
            $newSite->options->feed_limit = (int)$config['feed_limit'];
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

        $newSite->options->forceRebuild = true;
        $newSite->options->skipMedia = true;

        // Rebuild!
        $builder = new \Indieinabox\SiteBuilder($newSite);
        $builder->build();
        ob_end_clean();
        
        // Also update local site reference in handler
        $this->site = $newSite;
    }

    /**
     * Detects if the web server supports "pretty links" (URL rewriting).
     * Usually checks the presence of Apache's mod_rewrite via server variables.
     *
     * @return bool True if pretty links are supported, false otherwise.
     */
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

    /**
     * Renders the bootstrap (first-run) HTML form.
     * Prompts the user for a password, site title, and fully qualified domain name.
     *
     * @param string|null $error Optional error message to display on the form.
     * @return void
     */
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
            <style>
                :root {
                    --bg: #F4F1EA;
                    --fg: #2C2E2F;
                    --accent: #ef4444;
                }
                body {
                    background-color: var(--bg);
                    color: var(--fg);
                    font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                    line-height: 1.6;
                    max-width: 650px;
                    margin: 40px auto;
                    padding: 0 16px;
                }
                h1 {
                    color: var(--accent);
                }
                .error-message {
                    color: var(--accent);
                    margin-bottom: 1em;
                    font-weight: bold;
                }
                .form-group {
                    margin-bottom: 1.5em;
                }
                label {
                    display: block;
                    font-weight: bold;
                    margin-bottom: 0.5em;
                }
                input {
                    background: rgba(0, 0, 0, 0.05);
                    border: 1px solid var(--fg);
                    color: var(--fg);
                    padding: 8px 12px;
                    font-family: inherit;
                    width: 100%;
                    box-sizing: border-box;
                }
                button {
                    background: var(--fg);
                    color: var(--bg);
                    border: none;
                    padding: 10px 16px;
                    font-family: inherit;
                    cursor: pointer;
                    font-weight: bold;
                }
                button:hover {
                    background: var(--accent);
                }
            </style>
        </head>
        <body>
            <h1>Setup Setup Setup!</h1>
            <p>Indieinabox is not configured yet. Choose your password and site identity to get started.</p>

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
                    <input type="text" name="title" id="title" value="Aaron Schwartz" required>
                </div>

                <div class="form-group">
                    <label for="sitename">Site Name</label>
                    <input type="text" name="sitename" id="sitename" value="Aaron Schwartz's new social network" required>
                </div>

                <div class="form-group">
                    <label for="fqdn">Site FQDN (URL)</label>
                    <input type="url" name="fqdn" id="fqdn" value="<?= htmlspecialchars($detectedFqdn) ?>" required>
                </div>

                <button type="submit">Configure & Rebuild</button>
            </form>
        </body>
        </html>
        <?php
    }

    /**
     * Renders the main configuration form inside the admin panel.
     * Displays fields for metadata, kinds, translations, plugins, and shortlink settings.
     * Uses output buffering and includes the global admin layout.
     *
     * @return void
     */
    private function renderConfigForm(): void
    {
        $config = \Indieinabox\Database::getAllSettings();
        $config['kinds'] = \Indieinabox\Database::getKinds();
        $config['translations'] = \Indieinabox\Database::getTranslations();

        $langArr = $config['lang'] ?? ['en'];
        if (!is_array($langArr)) {
            $langArr = [$langArr];
        }
        $langStr = implode(', ', $langArr);
        
        $prettyLinksActive = $config['prettylinks'] ?? $this->detectPrettyLinksSupport();

        $activeTab = 'config';
        $adminLayoutPath = dirname(__DIR__) . '/resources/views/admin_layout.php';
        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');

        ob_start();
        ?>
            <style>
                :root {
                    --bg: transparent; /* Use parent background */
                    --fg: #2C2E2F;
                    --accent: #ef4444; /* red accent to signify config area */
                    --border-color: rgba(44, 46, 47, 0.2);
                }
                .config-wrapper {
                    background-color: #F4F1EA;
                    color: var(--fg);
                    font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                    line-height: 1.6;
                    padding: 2em;
                    min-height: 100%;
                }
                .config-wrapper .nav-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: baseline;
                    margin-bottom: 2em;
                    border-bottom: 1px solid var(--fg);
                    padding-bottom: 0.5em;
                }
                .config-wrapper h1 {
                    color: var(--accent);
                    margin: 0;
                }
                .config-wrapper a.logout-btn {
                    color: var(--fg);
                    text-decoration: underline;
                }
                .config-wrapper a.logout-btn:hover {
                    text-decoration: none;
                    color: var(--accent);
                }
                .alert-saved {
                    background: rgba(0, 255, 0, 0.1);
                    border: 1px dashed var(--fg);
                    padding: 1em;
                    margin-bottom: 1.5em;
                    font-weight: bold;
                }
                fieldset {
                    border: 1px solid var(--border-color);
                    border-radius: 8px;
                    padding: 1.5rem;
                    margin-bottom: 2rem;
                    background: rgba(255, 255, 255, 0.02);
                }

                .config-tabs {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 20px;
                    border-bottom: 1px solid var(--border-color);
                    padding-bottom: 10px;
                    overflow-x: auto;
                }

                .config-tab-btn {
                    background: transparent;
                    border: none;
                    color: var(--text-muted);
                    font-size: 1rem;
                    font-weight: 600;
                    padding: 8px 16px;
                    cursor: pointer;
                    border-radius: 6px;
                    transition: all 0.2s;
                }

                .config-tab-btn:hover {
                    color: var(--text-main);
                    background: rgba(255,255,255,0.05);
                }

                .config-tab-btn.active {
                    color: var(--accent);
                    background: rgba(236, 203, 0, 0.1);
                }

                .tab-content {
                    display: none;
                }

                .tab-content.active {
                    display: block;
                }

                legend {
                    font-weight: bold;
                    background: var(--bg);
                    padding: 0 8px;
                    color: var(--accent);
                }
                .form-group {
                    margin-bottom: 1.2em;
                }
                label {
                    display: block;
                    font-weight: bold;
                    margin-bottom: 0.3em;
                }
                input[type="text"],
                input[type="url"],
                input[type="password"],
                select,
                textarea {
                    width: 100%;
                    font-family: inherit;
                    background: var(--bg);
                    border: 1px solid var(--border-color);
                    color: var(--fg);
                    padding: 8px 12px;
                    box-sizing: border-box;
                }
                input[type="text"]:focus,
                input[type="url"]:focus,
                input[type="password"]:focus,
                select:focus,
                textarea:focus {
                    outline: none;
                    border-color: var(--accent);
                }
                textarea {
                    resize: vertical;
                    min-height: 80px;
                }
                .checkbox-group {
                    display: flex;
                    align-items: center;
                    gap: 0.5em;
                    cursor: pointer;
                    margin-bottom: 0.5em;
                }
                .checkbox-group input {
                    margin: 0;
                }
                .checkbox-group label {
                    display: inline;
                    margin: 0;
                    font-weight: normal;
                }
                button {
                    background: var(--fg);
                    color: var(--bg);
                    border: none;
                    padding: 10px 16px;
                    font-family: inherit;
                    cursor: pointer;
                    font-weight: bold;
                }
                button:hover {
                    background: var(--accent);
                }
                .btn-secondary {
                    background: transparent;
                    color: var(--fg);
                    border: 1px solid var(--fg);
                    padding: 6px 12px;
                    margin-top: 5px;
                }
                .btn-secondary:hover {
                    background: rgba(0,0,0,0.05);
                }
                .kind-card {
                    border: 1px solid var(--border-color);
                    padding: 1em;
                    margin-bottom: 1.5em;
                    background: var(--bg);
                }
                .kind-card h3 {
                    margin-top: 0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .grid-2 {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 1em;
                }
                .color-picker {
                    display: flex;
                    align-items: center;
                    gap: 0.5em;
                }
                .color-picker input[type="color"] {
                    height: 38px;
                    padding: 2px;
                }
            </style>
        <div class="config-wrapper">
            <div class="nav-header">
                <h1>Configuration Panel</h1>
                <a href="?action=logout" class="logout-btn">Log Out</a>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div class="alert-saved">
                    Settings saved successfully! Site has been automatically rebuilt.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['rebuilt'])): ?>
                <div class="alert-saved">
                    Site has been successfully rebuilt!
                </div>
            <?php endif; ?>

            <form action="" method="POST" id="configForm" enctype="multipart/form-data">
                <div class="config-tabs">
                    <button type="button" class="config-tab-btn active" onclick="showTab('tab-general')">General</button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-localization')">Localization</button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-kinds')">Content Kinds</button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-social')">Social & Federation</button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-services')">Services & Security</button>
                    <button type="button" class="config-tab-btn" onclick="showTab('tab-updates')">Updates</button>
                </div>

                <div id="tab-updates" class="tab-content">
                    <fieldset>
                        <legend>Dogfooding & Updates</legend>
                        <p style="font-size: 0.9em; margin-top: 0; color: var(--text-muted);">
                            Configure auto-updates or manually trigger updates. The list of versions is fetched asynchronously in the background.
                        </p>
                        <div class="grid-2">
                            <div class="form-group checkbox-group">
                                <label>
                                    <input type="hidden" name="auto_upgrade_stable" value="0">
                                    <input type="checkbox" name="auto_upgrade_stable" value="1" <?= !empty($config['auto_upgrade_stable']) ? 'checked' : '' ?>>
                                    Auto-upgrade Stable Releases
                                </label>
                            </div>
                            <div class="form-group checkbox-group">
                                <label>
                                    <input type="hidden" name="auto_upgrade_nightly" value="0">
                                    <input type="checkbox" name="auto_upgrade_nightly" value="1" <?= !empty($config['auto_upgrade_nightly']) ? 'checked' : '' ?>>
                                    Auto-upgrade Nightly Builds
                                </label>
                            </div>
                        </div>

                        <?php
                        $availableUpdates = \Indieinabox\Database::getSetting('available_updates', []);
                        $lastCheck = \Indieinabox\Database::getSetting('last_update_check', 0);
                        if (empty($availableUpdates)) {
                            echo '<p>No updates available or checking hasn\'t run yet. (Last check: ' . ($lastCheck ? date('Y-m-d H:i:s', $lastCheck) : 'Never') . ')</p>';
                        } else {
                            echo '<h4>Available Versions</h4><ul>';
                            foreach (array_slice($availableUpdates, 0, 5) as $update) {
                                $badge = $update['prerelease'] ? '<span style="background: #eab308; color: #000; padding: 2px 6px; border-radius: 4px; font-size: 0.8em;">Nightly</span>' : '<span style="background: #22c55e; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.8em;">Stable</span>';
                                echo '<li style="margin-bottom: 10px;">';
                                echo '<strong>' . htmlspecialchars($update['name']) . '</strong> ' . $badge;
                                echo '<br><small>Published: ' . htmlspecialchars($update['published_at']) . '</small>';
                                echo '<form method="POST" style="margin-top: 5px; display: inline-block;">';
                                echo '<input type="hidden" name="action" value="manual_update">';
                                echo '<input type="hidden" name="download_url" value="' . htmlspecialchars($update['download_url']) . '">';
                                echo '<button type="submit" class="btn" style="padding: 4px 10px; font-size: 0.9em;">Update to this version</button>';
                                echo '</form>';
                                echo '</li>';
                            }
                            echo '</ul>';
                        }
                        ?>

                        <?php
                        $backups = \Indieinabox\Updater::getLocalBackups();
                        if (!empty($backups)) {
                            echo '<h4>Local Backups (Rollback)</h4><ul>';
                            foreach ($backups as $bkp) {
                                echo '<li style="margin-bottom: 10px;">';
                                echo htmlspecialchars($bkp['filename']) . ' <small>(' . date('Y-m-d H:i:s', $bkp['date']) . ', ' . round($bkp['size'] / 1024, 2) . ' KB)</small>';
                                echo '<form method="POST" style="margin-top: 5px; display: inline-block; margin-left: 10px;">';
                                echo '<input type="hidden" name="action" value="rollback_update">';
                                echo '<input type="hidden" name="backup_filename" value="' . htmlspecialchars($bkp['filename']) . '">';
                                echo '<button type="submit" class="btn" style="padding: 4px 10px; font-size: 0.9em; background: var(--accent); color: white; border: none;">Rollback</button>';
                                echo '</form>';
                                echo '</li>';
                            }
                            echo '</ul>';
                        }
                        ?>
                    </fieldset>
                </div>

                <div id="tab-general" class="tab-content active">
                <fieldset>
                    <legend>General Settings</legend>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Site Title</label>
                            <input type="text" name="title" value="<?= htmlspecialchars($config['title'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Site Name</label>
                            <input type="text" name="sitename" value="<?= htmlspecialchars($config['sitename'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Author Name</label>
                            <input type="text" name="author" value="<?= htmlspecialchars($config['author'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Site FQDN (URL)</label>
                            <input type="url" name="fqdn" value="<?= htmlspecialchars($config['fqdn'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Base Path</label>
                        <input type="text" name="base" value="<?= htmlspecialchars($config['base'] ?? '/') ?>">
                    </div>
                </fieldset>

                <?php
                $themesDir = \Indieinabox\Database::$dataDir . '/themes';
                $availableThemes = ['default'];
                if (is_dir($themesDir)) {
                    $items = scandir($themesDir) ?: [];
                    foreach ($items as $item) {
                        if ($item !== '.' && $item !== '..' && is_dir($themesDir . '/' . $item)) {
                            $availableThemes[] = $item;
                        }
                    }
                }
                $activeTheme = $config['active_theme'] ?? 'default';
                
                $officialThemes = [
                    '' => '-- Select Theme --',
                    'https://github.com/lumen/theme-minimal/archive/refs/heads/main.zip' => 'Minimal Theme',
                    'https://github.com/lumen/theme-dark/archive/refs/heads/main.zip' => 'Dark Theme'
                ];
                ?>
                <fieldset>
                    <legend>Theme Settings</legend>
                    <div class="form-group">
                        <label>Active Theme</label>
                        <select name="active_theme">
                            <?php foreach ($availableThemes as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= $activeTheme === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="grid-2">
                        <div class="form-group" style="border: 1px solid var(--border-color); padding: 1em;">
                            <label>Install Official Theme</label>
                            <p style="font-size: 0.9em; color: #666; margin-top: 0;">Select a theme to download and install.</p>
                            <select name="install_official_theme">
                                <?php foreach ($officialThemes as $url => $name): ?>
                                    <option value="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="border: 1px solid var(--border-color); padding: 1em;">
                            <label>Upload Custom Theme (.zip)</label>
                            <p style="font-size: 0.9em; color: #666; margin-top: 0;">Upload your own theme zip file.</p>
                            <input type="file" name="custom_theme_zip" accept=".zip">
                        </div>
                    </div>
                    <small>Note: Installing or uploading a new theme will automatically extract it to your themes directory. You still need to set it as Active Theme above to use it.</small>
                </fieldset>

                <fieldset>
                    <legend>Build Options</legend>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Content Directory</label>
                            <input type="text" name="contentdir" value="<?= htmlspecialchars($config['contentdir'] ?? 'content') ?>">
                        </div>
                        <div class="form-group">
                            <label>Publish Directory</label>
                            <input type="text" name="outputdir" value="<?= htmlspecialchars($config['outputdir'] ?? 'public') ?>">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Default Category</label>
                            <input type="text" name="defaultcategory" value="<?= htmlspecialchars($config['defaultcategory'] ?? 'General') ?>">
                        </div>
                        <div class="form-group">
                            <label>HTML Postprocessing</label>
                            <select name="htmlpostprocessing">
                                <option value="none" <?= ($config['htmlpostprocessing'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                <option value="minify" <?= ($config['htmlpostprocessing'] ?? 'minify') === 'minify' ? 'selected' : '' ?>>Minify</option>
                                <option value="beautify" <?= ($config['htmlpostprocessing'] ?? '') === 'beautify' ? 'selected' : '' ?>>Beautify</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="feed_limit">RSS/Atom Feed Limit</label>
                        <input type="number" name="feed_limit" id="feed_limit" value="<?= (int)($config['feed_limit'] ?? 20) ?>" min="0">
                        <small>Number of latest posts to include in the RSS and Atom feeds. Set to 0 for unlimited.</small>
                    </div>
                    <div class="form-group">
                        <label>Supported File Extensions (comma separated)</label>
                        <input type="text" name="support" value="<?= htmlspecialchars(implode(', ', $config['support'] ?? ['md', 'txt', 'html', 'htm'])) ?>">
                    </div>
                    <div class="form-group">
                        <label>Languages</label>
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 1em; border: 1px solid var(--border-color);">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--fg); text-align: left; background: rgba(0,0,0,0.05);">
                                    <th style="padding: 8px;">Language Code</th>
                                    <th style="padding: 8px; width: 100px; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($langArr as $l): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 8px;">
                                        <?= htmlspecialchars($l) ?>
                                        <input type="hidden" name="lang[]" value="<?= htmlspecialchars($l) ?>">
                                    </td>
                                    <td style="padding: 8px; text-align: right;">
                                        <button type="submit" name="remove_lang" value="<?= htmlspecialchars($l) ?>" class="btn-secondary" style="margin: 0; padding: 4px 8px; font-size: 0.8rem;">Remove</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <button type="button" class="btn-secondary" onclick="addLanguage()">Add Language</button>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="prettylinks" id="prettylinks" <?= $prettyLinksActive ? 'checked' : '' ?>>
                            <label for="prettylinks">Pretty Links (folder/index.html format)</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="buildall" id="buildall" <?= ($config['buildall'] ?? true) ? 'checked' : '' ?>>
                            <label for="buildall">Build pages without frontmatter</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="dev" id="dev" <?= ($config['dev'] ?? false) ? 'checked' : '' ?>>
                            <label for="dev">Dev mode (live-reload script)</label>
                        </div>
                    </div>
                </fieldset>
                </div>

                <div id="tab-localization" class="tab-content">
                <fieldset>
                    <legend>Translations & Parity</legend>
                    <div class="form-group">
                        <label>Translation Parity Mode</label>
                        <select name="translation_parity">
                            <option value="full" <?= ($config['translation_parity'] ?? 'full') === 'full' ? 'selected' : '' ?>>Full (All directions)</option>
                            <option value="from-main-only" <?= ($config['translation_parity'] ?? '') === 'from-main-only' ? 'selected' : '' ?>>From Main Language Only</option>
                            <option value="from-sublang-only" <?= ($config['translation_parity'] ?? '') === 'from-sublang-only' ? 'selected' : '' ?>>From Sub-languages Only</option>
                            <option value="inter-sublang-only" <?= ($config['translation_parity'] ?? '') === 'inter-sublang-only' ? 'selected' : '' ?>>Inter Sub-languages Only</option>
                            <option value="disabled" <?= ($config['translation_parity'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                        </select>
                        <p style="font-size: 0.85em; opacity: 0.8; margin-top: 5px;">Controls which pages are required to have translations in other languages.</p>
                    </div>
                    <div class="form-group">
                        <label>Translation Auto-Generation</label>
                        <select name="translation_auto">
                            <option value="pseudo" <?= ($config['translation_auto'] ?? 'pseudo') === 'pseudo' ? 'selected' : '' ?>>Pseudo (Virtualizes translations with [LANG] prefix)</option>
                            <option value="disabled" <?= ($config['translation_auto'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled (Fails build if required parity is not met)</option>
                        </select>
                    </div>
                </fieldset>
                </div>

                <div id="tab-kinds" class="tab-content">
                <fieldset>
                    <legend>Content Kinds</legend>
                    <?php
                    $kinds = $config['kinds'] ?? [];
                    foreach ($kinds as $k => $data) {
                        ?>
                        <div class="kind-card">
                            <h3>
                                <?= htmlspecialchars($k) ?>
                                <button type="submit" name="remove_kind" value="<?= htmlspecialchars($k) ?>" class="btn-secondary" style="margin: 0; padding: 4px 8px; font-size: 0.8rem;">Remove</button>
                            </h3>
                            <div class="grid-2">
                                <div class="form-group">
                                    <label>Content Directory</label>
                                    <input type="text" name="kinds[<?= htmlspecialchars($k) ?>][content_dir]" value="<?= htmlspecialchars($data['content_dir'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Display Mode</label>
                                    <select name="kinds[<?= htmlspecialchars($k) ?>][display_mode]">
                                        <option value="default" <?= ($data['display_mode'] ?? 'default') === 'default' ? 'selected' : '' ?>>Default</option>
                                        <option value="full_content" <?= ($data['display_mode'] ?? '') === 'full_content' ? 'selected' : '' ?>>Full Content</option>
                                        <option value="thumbnail_snippet" <?= ($data['display_mode'] ?? '') === 'thumbnail_snippet' ? 'selected' : '' ?>>Thumbnail Snippet</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="grid-2">
                                <div class="form-group color-picker">
                                    <label>BG Color</label>
                                    <input type="color" name="kinds[<?= htmlspecialchars($k) ?>][palette][bg]" value="<?= htmlspecialchars($data['palette']['bg'] ?? '#ffffff') ?>">
                                </div>
                                <div class="form-group color-picker">
                                    <label>FG Color</label>
                                    <input type="color" name="kinds[<?= htmlspecialchars($k) ?>][palette][fg]" value="<?= htmlspecialchars($data['palette']['fg'] ?? '#000000') ?>">
                                </div>
                            </div>

                            <div class="checkbox-group">
                                <input type="checkbox" name="kinds[<?= htmlspecialchars($k) ?>][has_title]" id="kinds_<?= htmlspecialchars($k) ?>_ht" <?= !empty($data['has_title']) ? 'checked' : '' ?>>
                                <label for="kinds_<?= htmlspecialchars($k) ?>_ht">Has Title</label>
                                <input type="checkbox" name="kinds[<?= htmlspecialchars($k) ?>][show_on_home]" id="kinds_<?= htmlspecialchars($k) ?>_soh" <?= !empty($data['show_on_home']) ? 'checked' : '' ?>>
                                <label for="kinds_<?= htmlspecialchars($k) ?>_soh">Show on Home</label>
                                <input type="checkbox" name="kinds[<?= htmlspecialchars($k) ?>][show_in_menu]" id="kinds_<?= htmlspecialchars($k) ?>_sim" <?= (!isset($data['show_in_menu']) || !empty($data['show_in_menu'])) ? 'checked' : '' ?>>
                                <label for="kinds_<?= htmlspecialchars($k) ?>_sim">Show in Menu</label>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                    
                    <div class="kind-card" style="border: 2px dashed var(--border-color);">
                        <h3>➕ Add New Kind</h3>
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Kind ID (e.g. video)</label>
                                <input type="text" name="kinds[__new__][key]" placeholder="video">
                            </div>
                            <div class="form-group">
                                <label>Content Directory</label>
                                <input type="text" name="kinds[__new__][content_dir]" placeholder="videos">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Display Mode</label>
                            <select name="kinds[__new__][display_mode]">
                                <option value="default">Default</option>
                                <option value="full_content">Full Content</option>
                                <option value="thumbnail_snippet">Thumbnail Snippet</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Translations</label>
                            <div class="grid-2">
                                <?php foreach ($langArr as $l): ?>
                                <div class="color-picker" style="margin-bottom: 5px;">
                                    <span style="width: 50px;"><?= htmlspecialchars($l) ?></span>
                                    <input type="text" name="kinds[__new__][title][<?= htmlspecialchars($l) ?>]">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group color-picker">
                                <label>BG Color</label>
                                <input type="color" name="kinds[__new__][palette][bg]" value="#ffffff">
                            </div>
                            <div class="form-group color-picker">
                                <label>FG Color</label>
                                <input type="color" name="kinds[__new__][palette][fg]" value="#000000">
                            </div>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" name="kinds[__new__][has_title]" id="kinds_new_ht">
                            <label for="kinds_new_ht">Has Title</label>
                            <input type="checkbox" name="kinds[__new__][show_on_home]" id="kinds_new_soh">
                            <label for="kinds_new_soh">Show on Home</label>
                            <input type="checkbox" name="kinds[__new__][show_in_menu]" id="kinds_new_sim" checked>
                            <label for="kinds_new_sim">Show in Menu</label>
                        </div>
                    </div>
                </fieldset>
                </div>

                <div id="tab-social" class="tab-content">
                <fieldset>
                    <legend>TwTxt / Social Settings</legend>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Twtxt Nickname</label>
                            <input type="text" name="twtxt_nick" value="<?= htmlspecialchars($config['twtxt']['nick'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Twtxt Avatar URL</label>
                            <input type="url" name="twtxt_avatar" value="<?= htmlspecialchars($config['twtxt']['avatar'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Twtxt Description</label>
                        <input type="text" name="twtxt_description" value="<?= htmlspecialchars($config['twtxt']['description'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Subscribed Feeds (Format: `nickname feed_url` one per line)</label>
                        <?php
                        $followLines = [];
                        foreach (($config['twtxt']['following'] ?? []) as $f) {
                            $followLines[] = "{$f['nick']} {$f['url']}";
                        }
                        ?>
                        <textarea name="twtxt_following" placeholder="bob https://bob.com/twtxt.txt"><?= htmlspecialchars(implode("\n", $followLines)) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Configured Hubs (one URL per line)</label>
                        <textarea name="twtxt_hubs" placeholder="https://hub.twtxt.org"><?= htmlspecialchars(implode("\n", $config['twtxt']['hubs'] ?? [])) ?></textarea>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Federation (ActivityPub)</legend>
                    <div class="checkbox-group" style="margin-bottom: 1em;">
                        <input type="checkbox" name="activitypub_enabled" id="activitypub_enabled" <?= !empty($config['activitypub_enabled']) ? 'checked' : '' ?>>
                        <label for="activitypub_enabled">Enable ActivityPub Federation (Mastodon, Misskey, etc.)</label>
                    </div>
                    <div class="form-group">
                        <label>Fediverse Handle (e.g. 'schwartz')</label>
                        <input type="text" name="activitypub_handle" value="<?= htmlspecialchars($config['activitypub_handle'] ?? 'schwartz') ?>">
                        <small>Your full handle will be <code>@your_handle@your_fqdn</code></small>
                    </div>
                </fieldset>
                </div>

                <div id="tab-localization-global" class="tab-content">
                <fieldset>
                    <legend>Global Translations</legend>
                    <?php
                    $globalStrings = [
                        'Home' => 'Home link',
                        'Index' => 'Index link',
                        'Now' => 'Now link',
                        'Recent posts' => 'Recent posts header',
                        'Browse the sections of the site in Gopher style:' => 'Gopher section description',
                        'About' => 'About link',
                        'Maturity' => 'Maturity label',
                        'Reliability' => 'Reliability label',
                        'Articles' => 'Articles footer link / kind label fallback',
                        'Notes' => 'Notes footer link / kind label fallback',
                        'Photos' => 'Photos footer link / kind label fallback',
                        'Garden' => 'Garden footer link / kind label fallback',
                        'Shortlink' => 'Shortlink label',
                        'Like' => 'Singular for Like',
                        'Likes' => 'Plural for Likes',
                        'Repost' => 'Singular for Repost',
                        'Reposts' => 'Plural for Reposts',
                        'Reply' => 'Singular for Reply',
                        'Replies' => 'Plural for Replies',
                        'Interactions on' => 'Interactions page title prefix',
                        'Permalink' => 'Permalink text in replies',
                        'Flowerbed' => 'Flowerbed label for garden posts',
                        'Confidence' => 'Confidence label for garden posts',
                        'Importance' => 'Importance label for garden posts',
                        'Also on' => 'Syndication links prefix',
                        'In reply to' => 'IndieWeb context prefix',
                        'Liked' => 'IndieWeb context prefix',
                        'Reposted' => 'IndieWeb context prefix',
                        'Bookmarked' => 'IndieWeb context prefix',
                        'Watched' => 'IndieWeb context prefix',
                        'Read' => 'IndieWeb context prefix',
                        'Listened to' => 'IndieWeb context prefix',
                        'This page was automatically translated by AI.' => 'AI translation notice',
                        'This page was automatically translated by AI and revised by a human.' => 'AI translation notice (revised)'
                    ];
                    foreach ($globalStrings as $origText => $desc):
                    ?>
                        <div style="margin-bottom: 1.5em; border-bottom: 1px dashed var(--border-color); padding-bottom: 1em;">
                            <strong><?= htmlspecialchars($origText) ?></strong> <span style="font-size: 0.9em; opacity: 0.7;">(<?= htmlspecialchars($desc) ?>)</span>
                            <div class="grid-2" style="margin-top: 0.5em;">
                                <?php foreach ($langArr as $l): ?>
                                    <div class="color-picker" style="margin-bottom: 5px;">
                                        <span style="width: 50px;"><?= htmlspecialchars($l) ?></span>
                                        <input type="text" name="translations[<?= htmlspecialchars($origText) ?>][<?= htmlspecialchars($l) ?>]" value="<?= htmlspecialchars($config['translations'][$origText][$l] ?? '') ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </fieldset>

                <fieldset>
                    <legend>Content Kinds Translations</legend>
                    <?php foreach ($kinds as $k => $data): ?>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label><?= htmlspecialchars(ucfirst($k)) ?> Translations</label>
                            <div class="grid-2">
                                <?php foreach ($langArr as $l): ?>
                                <div class="color-picker" style="margin-bottom: 5px;">
                                    <span style="width: 50px;"><?= htmlspecialchars($l) ?></span>
                                    <input type="text" name="kinds[<?= htmlspecialchars($k) ?>][title][<?= htmlspecialchars($l) ?>]" value="<?= htmlspecialchars($data['title'][$l] ?? '') ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </fieldset>
                </div>

                <div id="tab-services" class="tab-content">
                <fieldset>
                    <legend>Shortlink Service</legend>
                    <div class="form-group">
                        <p style="margin-top: 0;"><small>If enabled, IndieInABox will attempt to automatically shorten your links using a remote service. If disabled, it will use a local short hash instead (e.g. <code>/s/a1b2c3d4</code>).</small></p>
                        <input type="checkbox" name="shortlink[enabled]" id="shortlink_enabled" value="1" <?= !empty($config['shortlink']['enabled']) ? 'checked' : '' ?>>
                        <label for="shortlink_enabled">Enable Remote Shortlinks (Nullpointer / Rustypaste compatible)</label>
                        <p class="help">If disabled, shortlinks will be generated locally (e.g. /s/abc12345).</p>
                        
                        <div style="margin-top: 1rem;">
                            <input type="checkbox" name="webarchive_enabled" id="webarchive_enabled" value="1" <?= !empty($config['webarchive_enabled']) ? 'checked' : '' ?>>
                            <label for="webarchive_enabled">Enable automatic WebArchive (archive.org) submissions</label>
                            <p class="help">If enabled, all external links in your posts will be automatically submitted to the Wayback Machine.</p>
                        </div>
                        
                        <div style="margin-top: 1rem;">
                            <input type="checkbox" name="webmention_enabled" id="webmention_enabled" value="1" <?= !empty($config['webmention_enabled']) ? 'checked' : '' ?>>
                            <label for="webmention_enabled">Enable automatic outgoing Webmentions</label>
                            <p class="help">If enabled, IndieInABox will attempt to notify other sites when you link to them.</p>
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Server URL</label>
                            <input type="url" name="shortlink[server]" value="<?= htmlspecialchars($config['shortlink']['server'] ?? 'https://0x0.st') ?>" placeholder="https://0x0.st">
                        </div>
                        <div class="form-group">
                            <label>POST Parameter Name</label>
                            <input type="text" name="shortlink[parameter]" value="<?= htmlspecialchars($config['shortlink']['parameter'] ?? 'shorten') ?>" placeholder="shorten or url">
                        </div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group">
                            <label>Auth Header Name (Optional)</label>
                            <input type="text" name="shortlink[auth_header]" value="<?= htmlspecialchars($config['shortlink']['auth_header'] ?? '') ?>" placeholder="e.g. Authorization">
                        </div>
                        <div class="form-group">
                            <label>Auth Token (Optional)</label>
                            <input type="text" name="shortlink[auth_token]" value="<?= htmlspecialchars($config['shortlink']['auth_token'] ?? '') ?>" placeholder="Token value">
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Spam Protection (Akismet)</legend>
                    <div class="form-group">
                        <label>Akismet API Key (Leave blank to disable)</label>
                        <input type="text" name="akismet_api_key" value="<?= htmlspecialchars((string)($config['akismet_api_key'] ?? '')) ?>" placeholder="Enter API Key">
                    </div>
                </fieldset>

                <fieldset style="border-color: var(--accent);">
                    <legend>Security</legend>
                    <div class="form-group">
                        <label>Change Admin Password (Optional)</label>
                        <input type="password" name="new_password" placeholder="Leave blank to keep current password">
                    </div>
                </fieldset>
                </div>

                <div class="submit-group" style="position: sticky; bottom: 0; background: var(--glass-bg, #111827); padding: 15px; border-top: 1px solid var(--border-color); z-index: 100; margin-top: 2rem; border-radius: 8px; display: flex; gap: 10px;">
                    <button type="submit" name="action" value="rebuild_site" class="btn" style="flex: 1; font-size: 1.2rem; padding: 15px; background: transparent; border: 1px solid var(--border-color); color: var(--fg);">Rebuild Only</button>
                    <button type="submit" class="save-btn btn" style="flex: 2; font-size: 1.2rem; padding: 15px;">Save Settings & Rebuild</button>
                </div>
            </form>

            <script>
                function showTab(tabId) {
                    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
                    document.querySelectorAll('.config-tab-btn').forEach(el => el.classList.remove('active'));
                    
                    if (tabId === 'tab-localization') {
                        document.getElementById('tab-localization').classList.add('active');
                        document.getElementById('tab-localization-global').classList.add('active');
                    } else {
                        document.getElementById(tabId).classList.add('active');
                    }
                    
                    event.currentTarget.classList.add('active');
                }

                function addLanguage() {
                    let langCode = prompt("Enter the new language code (e.g. fr, de):");
                    if (langCode) {
                        langCode = langCode.trim();
                        if (langCode.length > 0) {
                            let form = document.getElementById('configForm');
                            let input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'lang[]';
                            input.value = langCode;
                            form.appendChild(input);
                            form.submit();
                        }
                    }
                }
            </script>
        </div>
        <?php
        $content = ob_get_clean();
        \Indieinabox\ThemeManager::loadView($adminLayoutPath, [
            'content' => $content,
            'activeTab' => $activeTab,
            'fqdn' => $fqdn
        ]);
    }

    /**
     * Sends a plain-text HTTP error response.
     *
     * @param int $code The HTTP status code (e.g., 400, 401, 500).
     * @param string $message The error message to display.
     * @return void
     */
    private function sendError(int $code, string $message): void
    {
        header('HTTP/1.1 ' . $code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
    }
}
