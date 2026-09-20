<?php

declare(strict_types=1);

namespace Indieinabox\Http\Controllers;

use Indieinabox\BackgroundWorker\BackgroundWorker;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Services\ConfigurationService;
use Indieinabox\Services\MicrosubService;
use Indieinabox\Services\ModerationService;
use Indieinabox\Services\UpdateService;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\SiteBuilder;
use Indieinabox\Views\Admin\ConfigView;
use Indieinabox\Views\Admin\MicropubClientView;
use Indieinabox\Views\Admin\MicrosubReaderView;
use Indieinabox\Views\Admin\ModerationView;
use PDO;

/**
 * Controller managing administrative panels (settings, config, client, reader, moderation, cron).
 */
class AdminController extends AbstractController
{
    private ConfigurationService $configService;
    private ModerationService $moderationService;
    private ?MicrosubService $microsubService = null;
    private ?PDO $db;
    private ?SettingsRepositoryInterface $settingsRepo;

    public function __construct(
        Site $site,
        ?ConfigurationService $configService = null,
        ?ModerationService $moderationService = null,
        ?MicrosubService $microsubService = null,
        ?PDO $db = null,
        ?SettingsRepositoryInterface $settingsRepo = null
    ) {
        parent::__construct($site);
        if ($db !== null) {
            $this->db = $db;
        } else {
            try {
                $this->db = Container::getInstance()->has(PDO::class)
                    ? Container::getInstance()->get(PDO::class)
                    : (class_exists(Database::class) ? Database::getDb() : null);
            } catch (\Throwable) {
                $this->db = null;
            }
        }

        if ($settingsRepo !== null) {
            $this->settingsRepo = $settingsRepo;
        } else {
            try {
                $this->settingsRepo = Container::getInstance()->has(SettingsRepositoryInterface::class)
                    ? Container::getInstance()->get(SettingsRepositoryInterface::class)
                    : (class_exists(Database::class) ? Database::getSettingsRepository() : null);
            } catch (\Throwable) {
                $this->settingsRepo = null;
            }
        }

        $this->configService = $configService ?? new ConfigurationService($this->settingsRepo);
        $this->moderationService = $moderationService ?? new ModerationService();
        $this->microsubService = $microsubService;
    }

    public function getConfigurationService(): ConfigurationService
    {
        return $this->configService;
    }

    public function getModerationService(): ModerationService
    {
        return $this->moderationService;
    }

    public function getMicrosubService(): MicrosubService
    {
        if ($this->microsubService === null) {
            $this->microsubService = new MicrosubService($this->db, null, $this->site);
        }
        return $this->microsubService;
    }

    public function index(): void
    {
        $this->redirect('/admin/microsub');
    }

    /**
     * Admin site configuration endpoint.
     */
    public function config(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(43200);
            session_start();
        }

        $hasPassword = !empty($this->site->metadata->indieauthPassword);

        if (!$hasPassword) {
            $this->handleBootstrap();
            return;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'logout') {
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            session_destroy();
            $this->redirect('/admin/config');
            return;
        }

        if (isset($_GET['code']) && isset($_GET['state'])) {
            $this->handleCallback();
            return;
        }

        if (empty($_SESSION['admin_authenticated'])) {
            $this->redirectToAuth();
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            if (isset($_POST['action'])) {
                if ($_POST['action'] === 'manual_update' && !empty($_POST['download_url'])) {
                    UpdateService::downloadAndInstall((string) $_POST['download_url']);
                    $this->redirect('/admin/config?saved=1');
                    return;
                }
                if ($_POST['action'] === 'rollback_update' && !empty($_POST['backup_filename'])) {
                    UpdateService::rollback((string) $_POST['backup_filename']);
                    $this->redirect('/admin/config?saved=1');
                    return;
                }
                if ($_POST['action'] === 'rebuild_site') {
                    $this->rebuildSite();
                    $this->redirect('/admin/config?rebuilt=1');
                    return;
                }
            }
            $this->saveConfig();
            return;
        }

        $config = $this->settingsRepo ? $this->settingsRepo->all() : Database::getAllSettings();
        $config['kinds'] = $this->settingsRepo ? $this->settingsRepo->getKinds() : Database::getKinds();
        $config['translations'] = $this->settingsRepo ? $this->settingsRepo->getTranslations() : Database::getTranslations();
        $this->html(ConfigView::renderConfig($this->site, $config));
    }

    /**
     * Admin Micropub web posting client.
     */
    public function micropub(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(43200);
            session_start();
        }

        if (empty($_SESSION['admin_authenticated'])) {
            $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
            $this->redirect($fqdn . '/admin/config');
            return;
        }

        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $this->html(MicropubClientView::render($fqdn));
    }

    /**
     * Admin Microsub timeline reader.
     */
    public function microsub(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(43200);
            session_start();
        }

        if (empty($_SESSION['admin_authenticated'])) {
            $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
            $this->redirect($fqdn . '/admin/config');
            return;
        }

        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $this->html(MicrosubReaderView::render($fqdn));
    }

    /**
     * Admin interactions and comments moderation panel.
     */
    public function moderation(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(43200);
            session_start();
        }

        if (empty($_SESSION['admin_authenticated'])) {
            $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
            $this->redirect($fqdn . '/admin/config');
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $action = (string) ($_POST['action'] ?? '');
            $id = (string) ($_POST['id'] ?? '');
            $type = (string) ($_POST['type'] ?? 'pending');

            if ($action === 'approve') {
                $this->moderationService->approveInteraction($id, $type);
            } elseif ($action === 'delete') {
                $this->moderationService->deleteInteraction($id, $type);
            }

            $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
            $this->redirect($fqdn . '/admin/moderation');
            return;
        }

        $repo = $this->moderationService->getRepository();
        $pending = $repo->listByStatus('pending');
        $spam = $repo->listByStatus('spam');

        foreach ($pending as &$p) {
            $p['id_filename'] = $p['id'];
        }
        foreach ($spam as &$s) {
            $s['id_filename'] = $s['id'];
        }

        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $this->html(ModerationView::render($pending, $spam, $fqdn));
    }

    /**
     * Cron endpoint triggering background processing.
     */
    public function cron(): void
    {
        $worker = new BackgroundWorker($this->site);
        $worker->runAll();
        echo "OK";
    }

    private function handleBootstrap(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $password = (string) ($_POST['indieauth_password'] ?? '');
            $sitename = (string) ($_POST['sitename'] ?? 'My Site Name');
            $fqdn = (string) ($_POST['fqdn'] ?? '');

            if ($password === '') {
                $this->html(ConfigView::renderBootstrap('Password cannot be empty.'));
                return;
            }

            $this->configService->bootstrap($password, $sitename, $fqdn);
            $this->rebuildSite();
            $this->redirect('/admin/microsub');
            return;
        }

        $this->html(ConfigView::renderBootstrap());
    }

    private function redirectToAuth(): void
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['auth_state'] = $state;

        $fqdn = rtrim($this->site->metadata->fqdn ?? '', '/');
        $clientId = $fqdn . '/admin/config';
        $redirectUri = $fqdn . '/admin/config';

        $authUrl = $fqdn . '/auth'
            . '?client_id=' . urlencode($clientId)
            . '&redirect_uri=' . urlencode($redirectUri)
            . '&state=' . urlencode($state)
            . '&scope=config'
            . '&response_type=code';

        $this->redirect($authUrl);
    }

    private function handleCallback(): void
    {
        $state = (string) ($_GET['state'] ?? '');
        $code = (string) ($_GET['code'] ?? '');

        if (empty($_SESSION['auth_state']) || !hash_equals($_SESSION['auth_state'], $state)) {
            http_response_code(400);
            echo 'Invalid state parameter.';
            return;
        }

        $db = $this->db ?? Database::getDb();
        $codeHash = hash('sha256', $code);
        $stmt = $db->prepare('SELECT * FROM indieauth_codes WHERE code_hash = :hash');
        $stmt->bindValue(':hash', $codeHash);
        $stmt->execute();
        $codeData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$codeData) {
            http_response_code(400);
            echo 'Invalid or expired authorization code.';
            return;
        }

        $stmtDel = $db->prepare('DELETE FROM indieauth_codes WHERE code_hash = :hash');
        $stmtDel->bindValue(':hash', $codeHash);
        $stmtDel->execute();

        if ($codeData['expires_at'] < time()) {
            http_response_code(400);
            echo 'Authorization code has expired.';
            return;
        }

        $expectedClientId = rtrim($this->site->metadata->fqdn ?? '', '/') . '/admin/config';
        if (rtrim($codeData['client_id'], '/') !== rtrim($expectedClientId, '/')) {
            http_response_code(400);
            echo 'Client ID mismatch.';
            return;
        }

        $_SESSION['admin_authenticated'] = true;
        unset($_SESSION['auth_state']);

        $redirectUrl = $_SESSION['redirect_after_login'] ?? '/admin/microsub';
        unset($_SESSION['redirect_after_login']);

        $this->redirect($redirectUrl);
    }

    protected function saveConfig(): void
    {
        $currentConfig = $this->settingsRepo ? $this->settingsRepo->all() : Database::getAllSettings();
        $currentConfig['kinds'] = $this->settingsRepo ? $this->settingsRepo->getKinds() : Database::getKinds();
        $currentConfig['translations'] = $this->settingsRepo ? $this->settingsRepo->getTranslations() : Database::getTranslations();
        $currentConfig['urltranslations'] = $this->settingsRepo ? $this->settingsRepo->getUrlTranslations() : Database::getUrlTranslations();

        $base = trim((string) ($_POST['base'] ?? ''), '/');
        $currentConfig['base'] = (strlen($base) > 0 && $base !== '/') ? ('/' . ltrim($base, '/')) : '/';
        $currentConfig['sitename'] = trim((string) ($_POST['sitename'] ?? 'My Site Name'));
        $currentConfig['fqdn'] = rtrim(trim((string) ($_POST['fqdn'] ?? '')), '/');
        $currentConfig['author'] = trim((string) ($_POST['author'] ?? ''));
        $currentConfig['contentdir'] = !empty($_POST['contentdir']) ? trim((string) $_POST['contentdir']) : 'content';
        $currentConfig['outputdir'] = !empty($_POST['outputdir']) ? trim((string) $_POST['outputdir']) : 'public';
        $currentConfig['defaultcategory'] = trim((string) ($_POST['defaultcategory'] ?? 'General'));
        $currentConfig['htmlpostprocessing'] = (string) ($_POST['htmlpostprocessing'] ?? 'minify');
        $currentConfig['translation_parity'] = (string) ($_POST['translation_parity'] ?? 'full');
        $currentConfig['translation_auto'] = (string) ($_POST['translation_auto'] ?? 'pseudo');
        $currentConfig['akismet_api_key'] = trim((string) ($_POST['akismet_api_key'] ?? ''));
        $currentConfig['active_theme'] = trim((string) ($_POST['active_theme'] ?? 'default'));

        $currentConfig['buildall'] = isset($_POST['buildall']);
        $currentConfig['prettylinks'] = isset($_POST['prettylinks']);
        $currentConfig['activitypub_enabled'] = isset($_POST['activitypub_enabled']);
        $currentConfig['webmention_enabled'] = isset($_POST['webmention_enabled']);
        $currentConfig['webarchive_enabled'] = isset($_POST['webarchive_enabled']);
        $currentConfig['disable_shortlinks'] = isset($_POST['disable_shortlinks']);
        $currentConfig['download_media_image'] = isset($_POST['download_media_image']);
        $currentConfig['download_media_video'] = isset($_POST['download_media_video']);
        $currentConfig['download_media_audio'] = isset($_POST['download_media_audio']);
        $currentConfig['download_media_max_size_mb'] = isset($_POST['download_media_max_size_mb']) ? (float) $_POST['download_media_max_size_mb'] : 10.0;
        $currentConfig['skipstatic'] = isset($_POST['skipstatic']);
        $currentConfig['forcestaticoverride'] = isset($_POST['forcestaticoverride']);
        $currentConfig['auto_upgrade_stable'] = !empty($_POST['auto_upgrade_stable']);
        $currentConfig['auto_upgrade_nightly'] = !empty($_POST['auto_upgrade_nightly']);

        $currentConfig['backup_dir'] = trim((string) ($_POST['backup_dir'] ?? '../backup'));
        $currentConfig['backup_limit'] = isset($_POST['backup_limit']) ? max(1, (int) $_POST['backup_limit']) : 5;
        $currentConfig['backup_cron_enabled'] = isset($_POST['backup_cron_enabled']);
        $currentConfig['backup_skip_content'] = isset($_POST['backup_skip_content']);
        $currentConfig['backup_skip_media'] = isset($_POST['backup_skip_media']);

        $currentConfig['activitypub_handle'] = trim((string) ($_POST['activitypub_handle'] ?? 'schwartz'));
        $currentConfig['activitypub_cache_remote_emojis'] = isset($_POST['activitypub_cache_remote_emojis']);
        $currentConfig['feed_limit'] = isset($_POST['feed_limit']) ? (int) $_POST['feed_limit'] : 20;

        $supportVal = (string) ($_POST['support'] ?? 'md, txt, html, htm');
        $currentConfig['support'] = array_values(array_filter(array_map('trim', explode(',', $supportVal))));

        $langs = [];
        if (isset($_POST['lang'])) {
            if (is_array($_POST['lang'])) {
                $langs = array_values(array_filter(array_map('trim', $_POST['lang'])));
            } else {
                $langs = array_values(array_filter(array_map('trim', explode(',', (string) $_POST['lang']))));
            }
        }
        if (isset($_POST['remove_lang'])) {
            $removeLang = trim((string) $_POST['remove_lang']);
            $langs = array_values(array_filter($langs, fn($l) => $l !== $removeLang));
        }
        if (empty($langs)) {
            $langs = ['en'];
        }

        $oldLangs = $currentConfig['lang'] ?? ['en'];
        if (!is_array($oldLangs)) {
            $oldLangs = [$oldLangs];
        }
        $addedLangs = array_diff($langs, $oldLangs);
        $defaultOldLang = $oldLangs[0] ?? 'en';

        $currentConfig['lang'] = $langs;
        $currentConfig['defaultlang'] = $langs[0];

        $twtxtNick = trim((string) ($_POST['twtxt_nick'] ?? ''));
        $twtxtDesc = trim((string) ($_POST['twtxt_description'] ?? ''));
        $twtxtAvatar = trim((string) ($_POST['twtxt_avatar'] ?? ''));
        $following = [];
        foreach (explode("\n", (string) ($_POST['twtxt_following'] ?? '')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = preg_split('/\s+/', $line, 2);
            if (count($parts) === 2) {
                $following[] = ['nick' => $parts[0], 'url' => $parts[1]];
            }
        }
        $hubs = [];
        foreach (explode("\n", (string) ($_POST['twtxt_hubs'] ?? '')) as $line) {
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
            'hubs' => $hubs,
        ];

        if (isset($_POST['shortlink']) && is_array($_POST['shortlink'])) {
            $currentConfig['shortlink'] = [
                'enabled' => isset($_POST['shortlink']['enabled']),
                'server' => trim((string) ($_POST['shortlink']['server'] ?? '')),
                'parameter' => trim((string) ($_POST['shortlink']['parameter'] ?? 'shorten')),
                'auth_header' => trim((string) ($_POST['shortlink']['auth_header'] ?? '')),
                'auth_token' => trim((string) ($_POST['shortlink']['auth_token'] ?? '')),
            ];
        }

        $removeKind = isset($_POST['remove_kind']) ? trim((string) $_POST['remove_kind']) : null;
        if (isset($_POST['kinds']) && is_array($_POST['kinds'])) {
            $newKinds = [];
            foreach ($_POST['kinds'] as $k => $data) {
                if ($k === '__new__') {
                    if (empty($data['content_dir']) || empty($data['key'])) {
                        continue;
                    }
                    $k = trim((string) $data['key']);
                    if (empty($k)) {
                        continue;
                    }
                }
                if ($removeKind !== null && $k === $removeKind) {
                    continue;
                }

                $contentDirData = $data['content_dir'] ?? '';
                $cleanContentDir = [];
                if (is_array($contentDirData)) {
                    foreach ($contentDirData as $langCode => $dirName) {
                        $cleanContentDir[trim((string) $langCode)] = trim((string) $dirName);
                    }
                } else {
                    $cleanContentDir = trim((string) $contentDirData);
                }

                $newKinds[$k] = [
                    'content_dir' => $cleanContentDir,
                    'title' => $data['title'] ?? [],
                    'palette' => [
                        'bg' => trim((string) ($data['palette']['bg'] ?? '#ffffff')),
                        'fg' => trim((string) ($data['palette']['fg'] ?? '#000000')),
                    ],
                    'has_title' => isset($data['has_title']),
                    'show_on_home' => isset($data['show_on_home']),
                    'show_in_menu' => isset($data['show_in_menu']),
                    'display_mode' => trim((string) ($data['display_mode'] ?? 'default')),
                ];
            }

            if (empty($newKinds)) {
                $defaultKindTitle = [];
                $defaultContentDir = [];
                foreach ($langs as $l) {
                    $defaultKindTitle[$l] = 'Articles';
                    $defaultContentDir[$l] = 'articles';
                }
                $newKinds['article'] = [
                    'content_dir' => $defaultContentDir,
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
            $defaultContentDir = [];
            foreach ($langs as $l) {
                $defaultKindTitle[$l] = 'Articles';
                $defaultContentDir[$l] = 'articles';
            }
            $currentConfig['kinds'] = [
                'article' => [
                    'content_dir' => $defaultContentDir,
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

        if (isset($_POST['translations']) && is_array($_POST['translations'])) {
            $newTranslations = [];
            foreach ($_POST['translations'] as $origText => $langsData) {
                if (is_array($langsData)) {
                    foreach ($langsData as $langCode => $val) {
                        $newTranslations[trim((string) $origText)][trim((string) $langCode)] = trim((string) $val);
                    }
                }
            }
            $currentConfig['translations'] = $newTranslations;
        }

        if (isset($_POST['urltranslations']) && is_array($_POST['urltranslations'])) {
            $newUrlTranslations = [];
            foreach ($_POST['urltranslations'] as $origUrl => $langsData) {
                if (is_array($langsData)) {
                    foreach ($langsData as $langCode => $val) {
                        $newUrlTranslations[trim((string) $origUrl)][trim((string) $langCode)] = trim((string) $val);
                    }
                }
            }
            $currentConfig['urltranslations'] = $newUrlTranslations;
        }

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
                    if (isset($kindData['content_dir'])) {
                        if (is_string($kindData['content_dir'])) {
                            $baseDir = $kindData['content_dir'];
                            $kindData['content_dir'] = [];
                            foreach ($currentConfig['lang'] as $l) {
                                $kindData['content_dir'][$l] = $baseDir;
                            }
                        }
                        if (is_array($kindData['content_dir'])) {
                            foreach ($addedLangs as $nl) {
                                if (!isset($kindData['content_dir'][$nl]) || $kindData['content_dir'][$nl] === '') {
                                    $kindData['content_dir'][$nl] = $kindData['content_dir'][$defaultOldLang] ?? $k;
                                }
                            }
                        }
                    }
                }
                unset($kindData);
            }
        }

        if (!empty($_POST['new_password'])) {
            $currentConfig['indieauth_password'] = password_hash((string) $_POST['new_password'], PASSWORD_BCRYPT);
        }

        $db = $this->db ?? Database::getDb();
        foreach ($currentConfig as $key => $val) {
            if ($key === 'kinds' || $key === 'translations' || $key === 'urltranslations') {
                continue;
            }
            $this->configService->saveSetting((string) $key, $val);
        }

        $db->exec('DELETE FROM kinds');
        if (isset($currentConfig['kinds']) && is_array($currentConfig['kinds'])) {
            $newKindspath = [];
            foreach ($currentConfig['kinds'] as $k => $v) {
                if (isset($v['content_dir']) && is_string($v['content_dir'])) {
                    $decoded = json_decode($v['content_dir'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $v['content_dir'] = $decoded;
                    }
                }
                /** @psalm-suppress InvalidCast — $v may be non-array at runtime despite Psalm's inference */
                $valStr = is_array($v) ? (json_encode($v, JSON_UNESCAPED_UNICODE) ?: '') : (string) $v;
                $stmt = $db->prepare('INSERT INTO kinds (kind_key, config_json) VALUES (:k, :v)');
                $stmt->bindValue(':k', (string) $k);
                $stmt->bindValue(':v', $valStr);
                $stmt->execute();

                $cd = $v['content_dir'] ?? [];
                if (is_array($cd)) {
                    $newKindspath[$k] = array_values($cd);
                } else {
                    $newKindspath[$k] = [$cd];
                }
            }
            $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :val)');
            $stmt->bindValue(':key', 'kindspath');
            $stmt->bindValue(':val', json_encode($newKindspath, JSON_UNESCAPED_UNICODE));
            $stmt->execute();
        }

        if (isset($currentConfig['translations']) && is_array($currentConfig['translations'])) {
            foreach ($currentConfig['translations'] as $phraseKey => $langsData) {
                if (is_array($langsData)) {
                    foreach ($langsData as $lang => $phraseValue) {
                        $stmt = $db->prepare('SELECT id FROM translations WHERE lang = :lang AND phrase_key = :key');
                        $stmt->bindValue(':lang', $lang);
                        $stmt->bindValue(':key', $phraseKey);
                        $stmt->execute();
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($row) {
                            $upd = $db->prepare('UPDATE translations SET phrase_value = :val WHERE id = :id');
                            $upd->bindValue(':val', $phraseValue);
                            $upd->bindValue(':id', $row['id']);
                            $upd->execute();
                        } else {
                            $ins = $db->prepare('INSERT INTO translations (lang, phrase_key, phrase_value) VALUES (:lang, :key, :val)');
                            $ins->bindValue(':lang', $lang);
                            $ins->bindValue(':key', $phraseKey);
                            $ins->bindValue(':val', $phraseValue);
                            $ins->execute();
                        }
                    }
                }
            }
        }

        if (isset($currentConfig['urltranslations']) && is_array($currentConfig['urltranslations'])) {
            foreach ($currentConfig['urltranslations'] as $urlKey => $langsData) {
                if (is_array($langsData)) {
                    foreach ($langsData as $lang => $urlValue) {
                        $stmt = $db->prepare('SELECT id FROM url_translations WHERE lang = :lang AND slug_key = :key');
                        $stmt->bindValue(':lang', $lang);
                        $stmt->bindValue(':key', $urlKey);
                        $stmt->execute();
                        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                        if ($row) {
                            $upd = $db->prepare('UPDATE url_translations SET slug_value = :val WHERE id = :id');
                            $upd->bindValue(':val', $urlValue);
                            $upd->bindValue(':id', $row['id']);
                            $upd->execute();
                        } else {
                            $ins = $db->prepare('INSERT INTO url_translations (lang, slug_key, slug_value) VALUES (:lang, :key, :val)');
                            $ins->bindValue(':lang', $lang);
                            $ins->bindValue(':key', $urlKey);
                            $ins->bindValue(':val', $urlValue);
                            $ins->execute();
                        }
                    }
                }
            }
        }

        $this->rebuildSite();
        $this->redirect('/admin/config?saved=1');
    }

    public function rebuildSite(): void
    {
        ob_start();
        $basePath = $this->site->paths->baseDir;
        $config = $this->settingsRepo ? $this->settingsRepo->all() : Database::getAllSettings();
        $config['kinds'] = $this->settingsRepo ? $this->settingsRepo->getKinds() : Database::getKinds();
        $config['translations'] = $this->settingsRepo ? $this->settingsRepo->getTranslations() : Database::getTranslations();
        $config['urltranslations'] = $this->settingsRepo ? $this->settingsRepo->getUrlTranslations() : Database::getUrlTranslations();

        $newSite = new Site();
        $newSite->paths->baseDir = $basePath;
        $newSite->config = $config;

        if (isset($config['sitename'])) {
            $newSite->metadata->sitename = (string) $config['sitename'];
        }
        if (isset($config['author'])) {
            $newSite->metadata->author = (string) $config['author'];
        }
        if (isset($config['fqdn'])) {
            $newSite->metadata->fqdn = (string) $config['fqdn'];
        }
        if (isset($config['indieauth_password'])) {
            $newSite->metadata->indieauthPassword = (string) $config['indieauth_password'];
        }
        if (isset($config['support']) && is_array($config['support'])) {
            $newSite->support->support = $config['support'];
        }
        if (isset($config['buildall'])) {
            $newSite->options->buildAll = (bool) $config['buildall'];
        }
        if (isset($config['outputdir'])) {
            $baseOut = (string) $config['outputdir'];
            $newSite->paths->outputDirHtml = $baseOut . '_html';
            $newSite->paths->outputDirGemini = $baseOut . '_gemini';
            $newSite->paths->outputDirGopher = $baseOut . '_gopher';
            $newSite->paths->outputDirMedia = $baseOut . '_media';
        }
        if (isset($config['contentdir'])) {
            $newSite->paths->contentDir = (string) $config['contentdir'];
        }
        if (isset($config['defaultcategory'])) {
            $newSite->support->defaultCategory = (string) $config['defaultcategory'];
        }
        if (isset($config['htmlpostprocessing'])) {
            $newSite->options->htmlpostprocessing = (string) $config['htmlpostprocessing'];
        }
        if (isset($config['prettylinks'])) {
            $newSite->options->prettylinks = (bool) $config['prettylinks'];
        }
        if (isset($config['feed_limit'])) {
            $newSite->options->feed_limit = (int) $config['feed_limit'];
        }
        if (isset($config['lang'])) {
            $newSite->localization->lang = is_array($config['lang']) ? $config['lang'] : [$config['lang']];
            $newSite->localization->defaultLang = $newSite->localization->lang[0] ?? 'en';
        }

        $newSite->options->forceRebuild = true;
        $newSite->options->skipMedia = true;

        $builder = new SiteBuilder($newSite);
        $builder->build();
        ob_end_clean();

        $this->site = $newSite;
    }
}
