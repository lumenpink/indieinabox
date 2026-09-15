<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Core\Container;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Repositories\SqliteSettingsRepository;
use Indieinabox\Site;
use Indieinabox\SiteBuilder;
use PDO;
use ZipArchive;
use InvalidArgumentException;
use RuntimeException;

/**
 * Domain service managing site configuration, kind taxonomies, translations, and theme installations.
 */
class ConfigurationService
{
    private SettingsRepositoryInterface $settings;
    private ?PDO $db;

    public function __construct(
        SettingsRepositoryInterface|PDO|null $settings = null,
        ?PDO $db = null
    ) {
        if ($settings instanceof PDO) {
            $this->db = $settings;
            $this->settings = new SqliteSettingsRepository($settings);
        } elseif ($settings instanceof SettingsRepositoryInterface) {
            $this->settings = $settings;
            $this->db = $db;
        } else {
            $this->db = $db;
            $this->settings = class_exists(Container::class)
                ? Container::getInstance()->make(SettingsRepositoryInterface::class)
                : new SqliteSettingsRepository($db);
        }
    }

    public function getSettingsRepository(): SettingsRepositoryInterface
    {
        return $this->settings;
    }

    /**
     * Initializes a fresh site installation with default configuration and admin credentials.
     */
    public function bootstrap(string $password, string $sitename = 'My Site Name', string $fqdn = ''): void
    {
        if (empty($password)) {
            throw new InvalidArgumentException('Password cannot be empty.');
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
        return $this->settings->all();
    }

    /**
     * Saves or replaces a setting value in the database.
     */
    public function saveSetting(string $key, mixed $value): bool
    {
        return $this->settings->set($key, $value);
    }

    /**
     * Retrieves all kind configurations.
     *
     * @return array<string, mixed>
     */
    public function getKinds(): array
    {
        return $this->settings->getKinds();
    }

    /**
     * Saves or replaces kind taxonomies.
     *
     * @param array<string, mixed> $kinds
     */
    public function saveKinds(array $kinds): bool
    {
        return $this->settings->saveKinds($kinds);
    }

    /**
     * Retrieves all language translations.
     *
     * @return array<string, mixed>
     */
    public function getTranslations(): array
    {
        return $this->settings->getTranslations();
    }

    /**
     * Saves translations table.
     *
     * @param array<string, mixed> $translations
     */
    public function saveTranslations(array $translations): bool
    {
        return $this->settings->saveTranslations($translations);
    }

    /**
     * Retrieves URL translations.
     *
     * @return array<string, mixed>
     */
    public function getUrlTranslations(): array
    {
        return $this->settings->getUrlTranslations();
    }

    /**
     * Saves URL slug translations table.
     *
     * @param array<string, mixed> $urlTranslations
     */
    public function saveUrlTranslations(array $urlTranslations): bool
    {
        return $this->settings->saveUrlTranslations($urlTranslations);
    }

    /**
     * Checks whether the web server environment supports clean pretty links.
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
     * Validates and installs an uploaded theme zip file.
     *
     * @param array<string, mixed> $file Uploaded $_FILES entry.
     * @param string $themesDir Target destination directory.
     * @return string Installed theme folder name.
     */
    public function installTheme(array $file, string $themesDir): string
    {
        if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            throw new InvalidArgumentException('No valid theme archive uploaded.');
        }

        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive extension is required for theme installation.');
        }

        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            throw new RuntimeException('Failed to open uploaded ZIP file.');
        }

        $themeName = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if ($filename !== false) {
                $parts = explode('/', trim($filename, '/'));
                if (!empty($parts[0])) {
                    $themeName = $parts[0];
                    break;
                }
            }
        }

        if (empty($themeName)) {
            $zip->close();
            throw new RuntimeException('Could not determine theme name from archive structure.');
        }

        if (!is_dir($themesDir)) {
            @mkdir($themesDir, 0755, true);
        }

        $extracted = $zip->extractTo($themesDir);
        $zip->close();

        if (!$extracted) {
            throw new RuntimeException('Failed to extract theme archive to themes directory.');
        }

        return $themeName;
    }

    /**
     * Triggers a complete static site generation rebuild.
     */
    public function triggerRebuild(?Site $site = null): void
    {
        if ($site === null && class_exists(Site::class)) {
            $site = new Site();
        }

        if ($site !== null && class_exists(SiteBuilder::class)) {
            $builder = new SiteBuilder($site);
            $builder->build();
        }
    }
}
