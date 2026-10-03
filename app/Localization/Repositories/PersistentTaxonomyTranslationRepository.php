<?php

declare(strict_types=1);

namespace Indieinabox\Localization\Repositories;

use Indieinabox\Core\Database;
use PDO;
use Throwable;

/**
 * Class PersistentTaxonomyTranslationRepository
 *
 * Persists and resolves AI translations for titles, slugs, flowerbeds (canteiros),
 * tags, and categories in SQLite to guarantee 100% stable permalinks across builds.
 */
class PersistentTaxonomyTranslationRepository
{
    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
        $this->ensureSchema();
    }

    private function getPdo(): ?PDO
    {
        if ($this->db !== null) {
            return $this->db;
        }
        try {
            $pdo = Database::getDb();
            if ($pdo !== null) {
                return $pdo;
            }
        } catch (Throwable) {
            // fallback below
        }

        // In-memory fallback if global DB is not initialized (e.g. during standalone unit tests)
        static $fallbackDb = null;
        if ($fallbackDb === null) {
            try {
                $fallbackDb = new PDO('sqlite::memory:');
                $fallbackDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $fallbackDb->exec('CREATE TABLE IF NOT EXISTS ai_taxonomy_translations (
                    source_lang TEXT NOT NULL,
                    target_lang TEXT NOT NULL,
                    taxonomy_type TEXT NOT NULL,
                    source_term TEXT NOT NULL,
                    translated_term TEXT NOT NULL,
                    created_at INTEGER NOT NULL,
                    PRIMARY KEY (source_lang, target_lang, taxonomy_type, source_term)
                )');
            } catch (Throwable) {
                return null;
            }
        }
        return $fallbackDb;
    }

    private function ensureSchema(): void
    {
        $pdo = $this->getPdo();
        if (!$pdo) {
            return;
        }

        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS ai_taxonomy_translations (
                source_lang TEXT NOT NULL,
                target_lang TEXT NOT NULL,
                taxonomy_type TEXT NOT NULL,
                source_term TEXT NOT NULL,
                translated_term TEXT NOT NULL,
                created_at INTEGER NOT NULL,
                PRIMARY KEY (source_lang, target_lang, taxonomy_type, source_term)
            )');
        } catch (Throwable) {
            // Ignore if in-memory or locked
        }
    }

    /**
     * Looks up an existing translated term from persistent storage.
     */
    public function get(string $sourceLang, string $targetLang, string $type, string $sourceTerm): ?string
    {
        $pdo = $this->getPdo();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT translated_term FROM ai_taxonomy_translations 
                 WHERE source_lang = ? AND target_lang = ? AND taxonomy_type = ? AND source_term = ?'
            );
            $stmt->execute([$sourceLang, $targetLang, $type, trim($sourceTerm)]);
            $val = $stmt->fetchColumn();
            return is_string($val) && $val !== '' ? $val : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Persists a translated term mapping into SQLite.
     */
    public function set(string $sourceLang, string $targetLang, string $type, string $sourceTerm, string $translatedTerm): void
    {
        $pdo = $this->getPdo();
        if (!$pdo) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT OR REPLACE INTO ai_taxonomy_translations 
                 (source_lang, target_lang, taxonomy_type, source_term, translated_term, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $sourceLang,
                $targetLang,
                $type,
                trim($sourceTerm),
                trim($translatedTerm),
                time(),
            ]);
        } catch (Throwable) {
            // Graceful fallback
        }
    }

    /**
     * Returns all mappings for a specific taxonomy type between two languages.
     *
     * @return array<string, string>
     */
    public function getAllForType(string $sourceLang, string $targetLang, string $type): array
    {
        $pdo = $this->getPdo();
        if (!$pdo) {
            return [];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT source_term, translated_term FROM ai_taxonomy_translations 
                 WHERE source_lang = ? AND target_lang = ? AND taxonomy_type = ?'
            );
            $stmt->execute([$sourceLang, $targetLang, $type]);
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }
}
