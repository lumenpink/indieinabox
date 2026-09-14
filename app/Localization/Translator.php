<?php

declare(strict_types=1);

namespace Indieinabox\Localization;

use Indieinabox\Database;
use Indieinabox\Page;
use Indieinabox\Support\FileUtils;
use Indieinabox\Support\TextParser;

/**
 * Class Translator
 *
 * Handles i18n translation lookups, pluralization, database synchronization,
 * and slugized translation transformations.
 */
class Translator
{
    /**
     * Translation lookup
     *
     * @param string $text
     * @param string|null $lang
     * @return string
     */
    public static function translate(string $text, ?string $lang = null): string
    {
        global $translations, $page, $p, $site;
        if ($translations === null) {
            $translations = Database::getTranslations();
        }

        if ($lang === null) {
            if (isset($p)) {
                $lang = $p instanceof Page ? $p->lang : ($p["lang"] ?? "en");
            } elseif (isset($page)) {
                $lang = $page instanceof Page ? $page->lang : ($page["lang"] ?? "en");
            } else {
                $lang = "en";
            }
        }

        // 1. Try config-based translations first
        $found = null;
        if ($site && !empty($site->config['translations'])) {
            foreach ($site->config['translations'] as $original => $langs) {
                if (strcasecmp($original, $text) === 0) {
                    $found = $original;
                    if (isset($langs[$lang]) && $langs[$lang] !== '') {
                        return $langs[$lang];
                    }
                    break;
                }
                foreach ($langs as $lVal) {
                    if ($lVal !== '' && strcasecmp($lVal, $text) === 0) {
                        $found = $original;
                        if ($lang === ($site->localization->defaultLang ?? 'en')) {
                            return $original;
                        }
                        if (isset($langs[$lang]) && $langs[$lang] !== '') {
                            return $langs[$lang];
                        }
                        break 2;
                    }
                }
            }
        }

        if ($site && $lang === ($site->localization->defaultLang ?? 'en')) {
            return $text;
        }

        // If not found in the target language, we might need to insert a blank translation row
        // so it appears in the admin panel.
        if ($found === null || !isset($site->config['translations'][$found][$lang])) {
            try {
                $db = Database::getDb();
                $ins = $db->prepare(
                    'INSERT INTO translations (lang, phrase_key, phrase_value) VALUES (:lang, :key, :val)'
                );
                $ins->bindValue(':lang', $lang);
                $ins->bindValue(':key', $text);
                $ins->bindValue(':val', '');
                $ins->execute();

                // Update runtime config to avoid inserting again
                if ($site) {
                    if (!isset($site->config['translations'][$text])) {
                        $site->config['translations'][$text] = [];
                    }
                    $site->config['translations'][$text][$lang] = '';
                }
            } catch (\Exception $e) {
                // Ignore unique constraints if any
            }
        }

        return $text;
    }

    /**
     * Translation lookup with pluralization support
     *
     * @param string $singular
     * @param string $plural
     * @param int $count
     * @param string|null $lang
     * @return string
     */
    public static function translatePlural(
        string $singular,
        string $plural,
        int $count,
        ?string $lang = null
    ): string {
        $text = $count === 1 ? $singular : $plural;
        return self::translate($text, $lang);
    }

    /**
     * Translate and make lowercase
     *
     * @param string $text
     * @return string
     */
    public static function translateLowercase(string $text): string
    {
        return strtolower(self::translate($text));
    }

    /**
     * Translate and slugize
     *
     * @param string $text
     * @return string
     */
    public static function translateSlugize(string $text): string
    {
        return TextParser::slugize(self::translate($text));
    }

    /**
     * Update translations file / database
     *
     * @return void
     */
    public static function updateTranslations(): void
    {
        global $translations;
        FileUtils::recursiveKsort($translations);
        $db = Database::getDb();
        $db->beginTransaction();
        foreach ($translations as $lang => $phrases) {
            foreach ($phrases as $key => $val) {
                // Check if exists
                $stmt = $db->prepare('SELECT id FROM translations WHERE lang = :lang AND phrase_key = :key');
                if ($stmt) {
                    $stmt->bindValue(':lang', $lang);
                    $stmt->bindValue(':key', $key);
                    $stmt->execute();
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);

                    if ($row) {
                        $upd = $db->prepare('UPDATE translations SET phrase_value = :val WHERE id = :id');
                        $upd->bindValue(':val', $val);
                        $upd->bindValue(':id', $row['id']);
                        $upd->execute();
                    } else {
                        $ins = $db->prepare(
                            'INSERT INTO translations (lang, phrase_key, phrase_value) VALUES (:lang, :key, :val)'
                        );
                        $ins->bindValue(':lang', $lang);
                        $ins->bindValue(':key', $key);
                        $ins->bindValue(':val', $val);
                        $ins->execute();
                    }
                }
            }
        }
        $db->commit();
    }
}
