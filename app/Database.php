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
