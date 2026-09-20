<?php

declare(strict_types=1);

namespace Indieinabox\Localization;

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Page\Page;
use Indieinabox\Repositories\Contracts\SettingsRepositoryInterface;
use Indieinabox\Site\Site;
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
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        $site = $container && $container->has(Site::class) ? $container->get(Site::class) : ($GLOBALS['site'] ?? null);
        $container && $container->has(SettingsRepositoryInterface::class) ? $container->get(SettingsRepositoryInterface::class) : null;

        if ($lang === null) {
            $contextPage = $container && $container->has(Page::class) ? $container->get(Page::class) : ($GLOBALS['p'] ?? ($GLOBALS['page'] ?? null));
            if ($contextPage instanceof Page) {
                $lang = $contextPage->lang;
            } elseif (is_array($contextPage) && isset($contextPage['lang'])) {
                $lang = (string) $contextPage['lang'];
            } else {
                $lang = $site ? ($site->localization->defaultLang ?? 'en') : 'en';
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
    public static function updateTranslations(?array $translations = null): void
    {
        $container = class_exists(Container::class) ? Container::getInstance() : null;
        $settingsRepo = $container && $container->has(SettingsRepositoryInterface::class) ? $container->get(SettingsRepositoryInterface::class) : null;
        $trans = $translations ?? ($settingsRepo ? $settingsRepo->getTranslations() : (class_exists(Database::class) && Database::isConnected() ? Database::getTranslations() : ($GLOBALS['translations'] ?? [])));
        FileUtils::recursiveKsort($trans);
        $db = $container && $container->has(\PDO::class) ? $container->get(\PDO::class) : Database::getDb();
        $db->beginTransaction();
        foreach ($trans as $lang => $phrases) {
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
