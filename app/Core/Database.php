<?php

declare(strict_types=1);

namespace Indieinabox\Core;

use Exception;
use PDO;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Repositories\SqliteSettingsRepository;

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
    private static ?SettingsRepositoryInterface $settingsRepo = null;

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

            // Ensure webmention discovery cache table exists
            self::$db->exec('CREATE TABLE IF NOT EXISTS webmention_discovery_cache (
                domain TEXT PRIMARY KEY,
                supports_webmention INTEGER NOT NULL DEFAULT 0,
                last_checked INTEGER NOT NULL
            )');

            if (class_exists(Container::class)) {
                Container::getInstance()->instance(PDO::class, self::$db);
            }
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
     * Checks if a database connection is actively open.
     */
    public static function isConnected(): bool
    {
        return self::$db !== null;
    }

    /**
     * Closes the active PDO database connection.
     */
    public static function disconnect(): void
    {
        self::$db = null;
        self::$settingsRepo = null;
        if (class_exists(Container::class)) {
            Container::getInstance()->forget(PDO::class);
            Container::getInstance()->forget(SettingsRepositoryInterface::class);
        }
    }

    /**
     * Resolves the active settings repository.
     */
    public static function getSettingsRepository(): SettingsRepositoryInterface
    {
        if (self::$settingsRepo !== null) {
            return self::$settingsRepo;
        }

        if (class_exists(Container::class)) {
            try {
                return Container::getInstance()->get(SettingsRepositoryInterface::class);
            } catch (\Throwable) {
                // Fallback to direct resolution
            }
        }

        return new SqliteSettingsRepository(self::$db);
    }

    /**
     * Overrides the active settings repository (useful for testing and dependency injection).
     */
    public static function setSettingsRepository(?SettingsRepositoryInterface $repo): void
    {
        self::$settingsRepo = $repo;
    }

    /**
     * Fetches a single value from the settings table.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getSetting(string $key, mixed $default = null): mixed
    {
        return self::getSettingsRepository()->get($key, $default);
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
        return self::getSettingsRepository()->set($key, $value);
    }

    /**
     * Retrieves all rows from the settings table as an associative array.
     * JSON values are automatically decoded into PHP arrays.
     *
     * @return array<string, mixed> Key-value pairs of all site settings.
     */
    public static function getAllSettings(): array
    {
        return self::getSettingsRepository()->all();
    }

    /**
     * Fetches interface translations from the database.
     * Returns an array grouped by phrase key, containing mappings for each language.
     *
     * @return array<string, array<string, string>> Array of translations.
     */
    public static function getTranslations(): array
    {
        return self::getSettingsRepository()->getTranslations();
    }

    /**
     * Fetches localized URL slugs translations.
     * Groups results by the internal slug key, mapping it to localized values.
     *
     * @return array<string, array<string, string>> Array of URL translations.
     */
    public static function getUrlTranslations(): array
    {
        return self::getSettingsRepository()->getUrlTranslations();
    }

    /**
     * Retrieves content kind configurations (e.g., article, note, photo).
     * Decodes the JSON configuration column for each kind into an array.
     *
     * @return array<string, array<string, mixed>> Associative array of kind configs.
     */
    public static function getKinds(): array
    {
        return self::getSettingsRepository()->getKinds();
    }

    /**
     * Retrieves the database schema SQL.
     */
    public static function getSchemaSql(): string
    {
        global $__SQL_SCHEMA__;
        if (!empty($__SQL_SCHEMA__)) {
            return (string) $__SQL_SCHEMA__;
        }

        $candidates = [
            dirname(__DIR__, 2) . '/database.sql',
            dirname(__DIR__) . '/database.sql',
            (defined('DS') ? dirname(__DIR__) : dirname(__DIR__, 2)) . '/database.sql',
        ];

        foreach ($candidates as $file) {
            if (file_exists($file)) {
                return (string) file_get_contents($file);
            }
        }

        return '';
    }

    /**
     * Initializes database schema if not already initialized.
     */
    public static function initializeSchema(?PDO $db = null, ?string $sql = null): void
    {
        $targetDb = $db ?? self::getDb();
        $schemaSql = $sql ?? self::getSchemaSql();
        if ($schemaSql !== '') {
            $targetDb->exec($schemaSql);
        }
    }
}
