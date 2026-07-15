<?php

declare(strict_types=1);

namespace Indieinabox;

use Exception;
use PDO;
use PDOResult;

/**
 * Class Database
 * 
 * Provides a singleton PDO connection to the SQLite database and offers helper 
 * methods for fetching settings, translations, and content configurations.
 */
class Database
{
    private static ?PDO $db = null;
    public static ?string $dataDir = null;

    /**
     * Connects to the SQLite database and initializes connection attributes.
     * Sets PRAGMAs for WAL mode and foreign keys for optimized concurrent usage.
     *
     * @param string $path Path to the SQLite database file.
     * @throws Exception If PDO extension is missing or connection fails.
     */
    public static function connect(string $path): void
    {
        if (self::$db !== null) {
            return;
        }

        if (!extension_loaded('pdo_sqlite')) {
            throw new Exception("PDO extension is not loaded.");
        }

        try {
            self::$db = new PDO('sqlite:' . $path, '', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            // Wait up to 5 seconds if the database is busy (locked) instead of throwing an immediate error
            self::$db->setAttribute(PDO::ATTR_TIMEOUT, 5);
            
            // Enable Write-Ahead Logging (WAL) for better concurrent read/write performance
            self::$db->exec('PRAGMA journal_mode = WAL;');
            
            // Synchronous NORMAL is perfectly safe in WAL mode and faster than FULL
            self::$db->exec('PRAGMA synchronous = NORMAL;');

            // Foreign keys
            self::$db->exec('PRAGMA foreign_keys = ON;');

            // Ensure outgoing webmentions table exists
            self::$db->exec('CREATE TABLE IF NOT EXISTS outgoing_webmentions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                source_url TEXT NOT NULL,
                target_url TEXT NOT NULL,
                status TEXT DEFAULT \'pending\',
                created_at INTEGER NOT NULL
            )');
        } catch (Exception $e) {
            throw new Exception("Failed to connect to database: " . $e->getMessage());
        }
    }

    /**
     * Retrieves the active PDO database connection.
     * Throws an exception if the connection has not been established yet.
     *
     * @return PDO The active PDO instance.
     * @throws Exception If the database is not connected.
     */
    public static function getDb(): PDO
    {
        if (self::$db === null) {
            throw new Exception("Database is not connected.");
        }
        return self::$db;
    }

    /**
     * Fetches a single value from the settings table
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getSetting(string $key, mixed $default = null): mixed
    {
        try {
            $stmt = self::getDb()->prepare('SELECT value FROM settings WHERE key = :key');
            if (!$stmt) {
                return $default;
            }
            $stmt->bindValue(':key', $key, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if ($row && isset($row['value'])) {
                    $value = $row['value'];
                    
                    // Try to decode JSON
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $decoded;
                    }
                    
                    return $value;
                }
            }
            return $default;
        } catch (Exception $e) {
            // Robust exception handling: log or return default so we don't crash the app
            error_log("Database error in getSetting: " . $e->getMessage());
            return $default;
        }
    }

    /**
     * Saves a setting value to the database.
     * Arrays and objects are automatically JSON-encoded.
     *
     * @param string $key
     * @param mixed $value
     * @return bool True on success, false on failure.
     */
    public static function saveSetting(string $key, mixed $value): bool
    {
        try {
            $db = self::getDb();
            $stmt = $db->prepare('INSERT INTO settings (key, value) VALUES (:key, :value) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
            if (!$stmt) {
                return false;
            }
            
            $encodedValue = (is_array($value) || is_object($value)) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string)$value;
            $stmt->bindValue(':key', $key, PDO::PARAM_STR);
            $stmt->bindValue(':value', $encodedValue, PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Database error in saveSetting: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves all rows from the settings table as an associative array.
     * JSON values are automatically decoded into PHP arrays.
     *
     * @return array<string, mixed> Key-value pairs of all site settings.
     */
    public static function getAllSettings(): array
    {
        $settings = [];
        try {
            $result = self::getDb()->query('SELECT key, value FROM settings');
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

            // Load .env overrides
            $envPath = __DIR__ . '/../.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, '#') === 0 || empty($line)) continue;
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $envKey = trim($parts[0]);
                        $envVal = trim(trim($parts[1]), '"\'');
                        if (in_array($envKey, ['APP_URL', 'FQDN'])) {
                            $settings['fqdn'] = $envVal;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Database error in getAllSettings: " . $e->getMessage());
        }
        return $settings;
    }

    /**
     * Fetches interface translations from the database.
     * Returns an array grouped by phrase key, containing mappings for each language.
     *
     * @return array<string, array<string, string>> Array of translations.
     */
    public static function getTranslations(): array
    {
        $translations = [];
        try {
            $result = self::getDb()->query('SELECT lang, phrase_key, phrase_value FROM translations');
            if ($result) {
                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    $lang = $row['lang'];
                    $key = $row['phrase_key'];
                    $val = $row['phrase_value'];
                    if (!isset($translations[$key])) $translations[$key] = [];
                    // Only overwrite if the new value is not empty, or if we don't have a value yet
                    if (!isset($translations[$key][$lang]) || ($translations[$key][$lang] === '' && $val !== '')) {
                        $translations[$key][$lang] = $val;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Database error in getTranslations: " . $e->getMessage());
        }
        return $translations;
    }

    /**
     * Fetches localized URL slugs translations.
     * Groups results by the internal slug key, mapping it to localized values.
     *
     * @return array<string, array<string, string>> Array of URL translations.
     */
    public static function getUrlTranslations(): array
    {
        $urlTranslations = [];
        try {
            $result = self::getDb()->query('SELECT lang, slug_key, slug_value FROM url_translations');
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
            error_log("Database error in getUrlTranslations: " . $e->getMessage());
        }
        return $urlTranslations;
    }

    /**
     * Retrieves content kind configurations (e.g., article, note, photo).
     * Decodes the JSON configuration column for each kind into an array.
     *
     * @return array<string, array<string, mixed>> Associative array of kind configs.
     */
    public static function getKinds(): array
    {
        $kinds = [];
        try {
            $result = self::getDb()->query('SELECT kind_key, config_json FROM kinds');
            if ($result) {
                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    $key = $row['kind_key'];
                    $json = $row['config_json'];
                    $decoded = json_decode($json, true);
                    $kinds[$key] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $json;
                }
            }
        } catch (Exception $e) {
            error_log("Database error in getKinds: " . $e->getMessage());
        }
        return $kinds;
    }

}
