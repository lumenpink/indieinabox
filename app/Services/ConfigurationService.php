<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Database;
use Indieinabox\Site;
use Indieinabox\SiteBuilder;
use PDO;
use ZipArchive;

/**
 * Domain service managing site configuration, kind taxonomies, translations, and theme installations.
 */
class ConfigurationService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getDb();
    }

    /**
     * Initializes a fresh site installation with default configuration and admin credentials.
     */
    public function bootstrap(string $password, string $sitename = 'My Site Name', string $fqdn = ''): void
    {
        if (empty($password)) {
            throw new \InvalidArgumentException('Password cannot be empty.');
        }

        if (empty($fqdn)) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $fqdn = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080');
        }
        $fqdn = rtrim($fqdn, '/');

        $newConfig = [
            'base' => '/',
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
            'prettylinks' => $this->detectPrettyLinksSupport(),
        ];

        foreach ($newConfig as $key => $val) {
            $this->saveSetting((string) $key, $val);
        }
    }

    /**
     * Retrieves all configured site settings.
     *
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        return Database::getAllSettings();
    }

    /**
     * Saves or replaces a setting value in the database.
     */
    public function saveSetting(string $key, mixed $value): bool
    {
        return Database::saveSetting($key, $value);
    }

    /**
     * Retrieves all kind configurations.
     *
     * @return array<string, mixed>
     */
    public function getKinds(): array
    {
        return Database::getKinds();
    }

    /**
     * Saves or replaces kind taxonomies.
     *
     * @param array<string, mixed> $kinds
     */
    public function saveKinds(array $kinds): bool
    {
        $stmt = $this->db->prepare('INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES (?, ?)');
        foreach ($kinds as $key => $conf) {
            $json = is_array($conf) ? json_encode($conf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $conf;
            $stmt->execute([(string) $key, $json]);
        }
        return true;
    }

    /**
     * Retrieves all language translations.
     *
     * @return array<string, mixed>
     */
    public function getTranslations(): array
    {
        return Database::getTranslations();
    }

    /**
     * Saves translations table.
     *
     * @param array<string, mixed> $translations
     */
    public function saveTranslations(array $translations): bool
    {
        $stmt = $this->db->prepare('INSERT OR REPLACE INTO translations (lang, phrase_key, phrase_value) VALUES (?, ?, ?)');
        foreach ($translations as $lang => $phrases) {
            if (is_array($phrases)) {
                foreach ($phrases as $k => $v) {
                    $stmt->execute([(string) $lang, (string) $k, (string) $v]);
                }
            }
        }
        return true;
    }

    /**
     * Retrieves URL translations.
     *
     * @return array<string, mixed>
     */
    public function getUrlTranslations(): array
    {
        return Database::getUrlTranslations();
    }

    /**
     * Saves URL slug translations.
     *
     * @param array<string, mixed> $urlTranslations
     */
    public function saveUrlTranslations(array $urlTranslations): bool
    {
        $stmt = $this->db->prepare('INSERT OR REPLACE INTO url_translations (lang, slug_key, slug_value) VALUES (?, ?, ?)');
        foreach ($urlTranslations as $lang => $slugs) {
            if (is_array($slugs)) {
                foreach ($slugs as $k => $v) {
                    $stmt->execute([(string) $lang, (string) $k, (string) $v]);
                }
            }
        }
        return true;
    }

    /**
     * Detects whether pretty links (clean URLs) are supported by the server environment.
     */
    public function detectPrettyLinksSupport(): bool
    {
        if (php_sapi_name() === 'cli' || php_sapi_name() === 'cli-server') {
            return true;
        }

        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
        if (stripos($serverSoftware, 'caddy') !== false || stripos($serverSoftware, 'nginx') !== false) {
            return true;
        }

        if (stripos($serverSoftware, 'apache') !== false) {
            if (function_exists('apache_get_modules')) {
                $modules = apache_get_modules();
                return in_array('mod_rewrite', $modules, true);
            }
            return true;
        }

        return false;
    }

    /**
     * Triggers static site generation.
     */
    public function rebuildSite(Site $site): void
    {
        $builder = new SiteBuilder($site);
        $builder->build();
    }

    /**
     * Installs a theme from a remote ZIP archive.
     */
    public function installThemeFromUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $tempZip = tempnam(sys_get_temp_dir(), 'theme_dl_');
        if ($tempZip === false) {
            return false;
        }

        $content = @file_get_contents($url);
        if ($content === false) {
            @unlink($tempZip);
            return false;
        }

        file_put_contents($tempZip, $content);
        $result = $this->installThemeFromZip($tempZip);
        @unlink($tempZip);

        return $result;
    }

    /**
     * Extracts a theme ZIP archive into the themes directory.
     */
    public function installThemeFromZip(string $zipPath): bool
    {
        if (!class_exists('ZipArchive') || !file_exists($zipPath)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return false;
        }

        $themeName = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if ($filename !== false && str_contains($filename, 'theme.json')) {
                $parts = explode('/', trim($filename, '/'));
                if (count($parts) >= 2) {
                    $themeName = $parts[0];
                    break;
                }
            }
        }

        if ($themeName === null) {
            $zip->close();
            return false;
        }

        $dataDir = Database::$dataDir !== '' ? Database::$dataDir : dirname(__DIR__, 2) . '/data';
        $themesDir = $dataDir . '/../resources/themes';
        if (!is_dir($themesDir)) {
            @mkdir($themesDir, 0755, true);
        }

        $extractPath = $themesDir . '/' . $themeName;
        $success = $zip->extractTo($themesDir);
        $zip->close();

        return $success && is_dir($extractPath);
    }
}
