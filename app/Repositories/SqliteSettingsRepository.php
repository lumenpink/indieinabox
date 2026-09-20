<?php

declare(strict_types=1);

namespace Indieinabox\Repositories;

use PDO;
use Exception;
use Indieinabox\Core\Database;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;

/**
 * SQLite-backed implementation of SettingsRepositoryInterface.
 */
class SqliteSettingsRepository implements SettingsRepositoryInterface
{
    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function getDb(): PDO
    {
        return $this->db ?? Database::getDb();
    }

    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $stmt = $this->getDb()->prepare('SELECT value FROM settings WHERE key = :key');
            if (!$stmt) {
                return $default;
            }
            $stmt->bindValue(':key', $key, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && isset($row['value'])) {
                $value = $row['value'];
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
                return $value;
            }

            return $default;
        } catch (Exception $e) {
            error_log("SqliteSettingsRepository::get error: " . $e->getMessage());
            return $default;
        }
    }

    #[\Override]
    public function set(string $key, mixed $value): bool
    {
        try {
            $stmt = $this->getDb()->prepare('INSERT INTO settings (key, value) VALUES (:key, :value) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
            if (!$stmt) {
                return false;
            }

            $encodedValue = (is_array($value) || is_object($value))
                ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : (string)$value;

            $stmt->bindValue(':key', $key, PDO::PARAM_STR);
            $stmt->bindValue(':value', $encodedValue, PDO::PARAM_STR);

            return $stmt->execute();
        } catch (Exception $e) {
            error_log("SqliteSettingsRepository::set error: " . $e->getMessage());
            return false;
        }
    }

    #[\Override]
    public function all(): array
    {
        $settings = [];
        try {
            $result = $this->getDb()->query('SELECT key, value FROM settings');
            if ($result) {
                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    $value = $row['value'];
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    }
                    $settings[$row['key']] = $value;
                }
            }

            // Load .env overrides if present
            $envPath = dirname(__DIR__, 2) . '/.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines) {
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (str_starts_with($line, '#') || empty($line)) {
                            continue;
                        }
                        $parts = explode('=', $line, 2);
                        if (count($parts) === 2) {
                            $envKey = trim($parts[0]);
                            $envVal = trim(trim($parts[1]), '"\'');
                            if (in_array($envKey, ['APP_URL', 'FQDN'], true)) {
                                $settings['fqdn'] = $envVal;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("SqliteSettingsRepository::all error: " . $e->getMessage());
        }
        return $settings;
    }

    #[\Override]
    public function getTranslations(): array
    {
        $translations = [];
        try {
            $result = $this->getDb()->query('SELECT lang, phrase_key, phrase_value FROM translations');
            if ($result) {
                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    $lang = $row['lang'];
                    $key = $row['phrase_key'];
                    $val = $row['phrase_value'];
                    if (!isset($translations[$key])) {
                        $translations[$key] = [];
                    }
                    if (!isset($translations[$key][$lang]) || ($translations[$key][$lang] === '' && $val !== '')) {
                        $translations[$key][$lang] = $val;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("SqliteSettingsRepository::getTranslations error: " . $e->getMessage());
        }
        return $translations;
    }

    #[\Override]
    public function getUrlTranslations(): array
    {
        $urlTranslations = [];
        try {
            $result = $this->getDb()->query('SELECT lang, slug_key, slug_value FROM url_translations');
            if ($result) {
                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    $lang = $row['lang'];
                    $key = $row['slug_key'];
                    $val = $row['slug_value'];

                    if (!isset($urlTranslations[$key])) {
                        $urlTranslations[$key] = [];
                    }
                    $urlTranslations[$key][$lang] = $val;
                }
            }
        } catch (Exception $e) {
            error_log("SqliteSettingsRepository::getUrlTranslations error: " . $e->getMessage());
        }
        return $urlTranslations;
    }

    #[\Override]
    public function getKinds(): array
    {
        $kinds = [];
        try {
            $result = $this->getDb()->query('SELECT kind_key, config_json FROM kinds');
            if ($result) {
                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    $key = $row['kind_key'];
                    $json = $row['config_json'];
                    $decoded = json_decode($json, true);
                    $kinds[$key] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $json;
                }
            }
        } catch (Exception $e) {
            error_log("SqliteSettingsRepository::getKinds error: " . $e->getMessage());
        }
        return $kinds;
    }

    #[\Override]
    public function saveKinds(array $kinds): bool
    {
        try {
            $db = $this->getDb();
            $stmt = $db->prepare('INSERT INTO kinds (kind_key, config_json) VALUES (:key, :json) ON CONFLICT(kind_key) DO UPDATE SET config_json = excluded.config_json');

            foreach ($kinds as $key => $config) {
                $json = is_string($config) ? $config : json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $stmt->execute([':key' => (string)$key, ':json' => $json]);
            }
            return true;
        } catch (Exception $e) {
            error_log("SqliteSettingsRepository::saveKinds error: " . $e->getMessage());
            return false;
        }
    }

    #[\Override]
    public function saveTranslations(array $translations): bool
    {
        try {
            $db = $this->getDb();
            $stmtDel = $db->prepare('DELETE FROM translations WHERE lang = :lang AND phrase_key = :key');
            $stmtIns = $db->prepare('INSERT INTO translations (phrase_key, lang, phrase_value) VALUES (:key, :lang, :val)');

            foreach ($translations as $firstKey => $subArray) {
                if (is_array($subArray)) {
                    foreach ($subArray as $secondKey => $val) {
                        if (strlen((string)$firstKey) === 2) {
                            $lang = (string)$firstKey;
                            $phraseKey = (string)$secondKey;
                        } else {
                            $phraseKey = (string)$firstKey;
                            $lang = (string)$secondKey;
                        }

                        $stmtDel->execute([':lang' => $lang, ':key' => $phraseKey]);
                        $stmtIns->execute([':key' => $phraseKey, ':lang' => $lang, ':val' => (string)$val]);
                    }
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("SqliteSettingsRepository::saveTranslations error: " . $e->getMessage());
            return false;
        }
    }

    #[\Override]
    public function saveUrlTranslations(array $urlTranslations): bool
    {
        try {
            $db = $this->getDb();
            $stmtDel = $db->prepare('DELETE FROM url_translations WHERE lang = :lang AND slug_key = :key');
            $stmtIns = $db->prepare('INSERT INTO url_translations (slug_key, lang, slug_value) VALUES (:key, :lang, :val)');

            foreach ($urlTranslations as $firstKey => $subArray) {
                if (is_array($subArray)) {
                    foreach ($subArray as $secondKey => $val) {
                        if (strlen((string)$firstKey) === 2) {
                            $lang = (string)$firstKey;
                            $slugKey = (string)$secondKey;
                        } else {
                            $slugKey = (string)$firstKey;
                            $lang = (string)$secondKey;
                        }

                        $stmtDel->execute([':lang' => $lang, ':key' => $slugKey]);
                        $stmtIns->execute([':key' => $slugKey, ':lang' => $lang, ':val' => (string)$val]);
                    }
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("SqliteSettingsRepository::saveUrlTranslations error: " . $e->getMessage());
            return false;
        }
    }
}
