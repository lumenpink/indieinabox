<?php

declare(strict_types=1);

namespace Indieinabox\Localization;

/**
 * Standard ISO 639-1 and BCP-47 language catalog providing localized names,
 * native endonyms, and short code formatting for multilingual websites.
 */
class IsoLanguages
{
    /**
     * Curated catalog of standard languages with their ISO 639-1 code,
     * English name, full native endonym, short native name, and short 2-letter uppercase representation.
     *
     * @var array<string, array{code: string, name: string, native_name: string, native_short: string, short_code: string}>
     */
    private static array $catalog = [
        'en'    => ['code' => 'en',    'name' => 'English',                 'native_name' => 'English',              'native_short' => 'English',   'short_code' => 'EN'],
        'en-US' => ['code' => 'en-US', 'name' => 'English (United States)', 'native_name' => 'English (US)',          'native_short' => 'English',   'short_code' => 'EN'],
        'en-GB' => ['code' => 'en-GB', 'name' => 'English (United Kingdom)', 'native_name' => 'English (UK)',          'native_short' => 'English',   'short_code' => 'EN'],
        'pt'    => ['code' => 'pt',    'name' => 'Portuguese',              'native_name' => 'Português',            'native_short' => 'Português', 'short_code' => 'PT'],
        'pt-BR' => ['code' => 'pt-BR', 'name' => 'Portuguese (Brazil)',     'native_name' => 'Português (Brasil)',   'native_short' => 'Português', 'short_code' => 'PT'],
        'pt-PT' => ['code' => 'pt-PT', 'name' => 'Portuguese (Portugal)',   'native_name' => 'Português (Portugal)', 'native_short' => 'Português', 'short_code' => 'PT'],
        'es'    => ['code' => 'es',    'name' => 'Spanish',                 'native_name' => 'Español',              'native_short' => 'Español',   'short_code' => 'ES'],
        'es-ES' => ['code' => 'es-ES', 'name' => 'Spanish (Spain)',         'native_name' => 'Español (España)',     'native_short' => 'Español',   'short_code' => 'ES'],
        'es-MX' => ['code' => 'es-MX', 'name' => 'Spanish (Mexico)',        'native_name' => 'Español (México)',     'native_short' => 'Español',   'short_code' => 'ES'],
        'fr'    => ['code' => 'fr',    'name' => 'French',                  'native_name' => 'Français',             'native_short' => 'Français',  'short_code' => 'FR'],
        'de'    => ['code' => 'de',    'name' => 'German',                  'native_name' => 'Deutsch',              'native_short' => 'Deutsch',   'short_code' => 'DE'],
        'it'    => ['code' => 'it',    'name' => 'Italian',                 'native_name' => 'Italiano',             'native_short' => 'Italiano',  'short_code' => 'IT'],
        'ja'    => ['code' => 'ja',    'name' => 'Japanese',                'native_name' => '日本語',                'native_short' => '日本語',    'short_code' => 'JA'],
        'zh'    => ['code' => 'zh',    'name' => 'Chinese',                 'native_name' => '中文',                  'native_short' => '中文',      'short_code' => 'ZH'],
        'zh-CN' => ['code' => 'zh-CN', 'name' => 'Chinese (Simplified)',    'native_name' => '简体中文',              'native_short' => '中文',      'short_code' => 'ZH'],
        'zh-TW' => ['code' => 'zh-TW', 'name' => 'Chinese (Traditional)',   'native_name' => '繁體中文',              'native_short' => '中文',      'short_code' => 'ZH'],
        'ko'    => ['code' => 'ko',    'name' => 'Korean',                  'native_name' => '한국어',                'native_short' => '한국어',    'short_code' => 'KO'],
        'ru'    => ['code' => 'ru',    'name' => 'Russian',                 'native_name' => 'Русский',               'native_short' => 'Русский',   'short_code' => 'RU'],
        'ar'    => ['code' => 'ar',    'name' => 'Arabic',                  'native_name' => 'العربية',               'native_short' => 'العربية',   'short_code' => 'AR'],
        'hi'    => ['code' => 'hi',    'name' => 'Hindi',                   'native_name' => 'हिन्दी',                 'native_short' => 'हिन्दी',     'short_code' => 'HI'],
        'nl'    => ['code' => 'nl',    'name' => 'Dutch',                   'native_name' => 'Nederlands',            'native_short' => 'Nederlands','short_code' => 'NL'],
        'pl'    => ['code' => 'pl',    'name' => 'Polish',                  'native_name' => 'Polski',                'native_short' => 'Polski',    'short_code' => 'PL'],
        'sv'    => ['code' => 'sv',    'name' => 'Swedish',                 'native_name' => 'Svenska',               'native_short' => 'Svenska',   'short_code' => 'SV'],
        'no'    => ['code' => 'no',    'name' => 'Norwegian',               'native_name' => 'Norsk',                 'native_short' => 'Norsk',     'short_code' => 'NO'],
        'da'    => ['code' => 'da',    'name' => 'Danish',                  'native_name' => 'Dansk',                 'native_short' => 'Dansk',     'short_code' => 'DA'],
        'fi'    => ['code' => 'fi',    'name' => 'Finnish',                 'native_name' => 'Suomi',                 'native_short' => 'Suomi',     'short_code' => 'FI'],
        'cs'    => ['code' => 'cs',    'name' => 'Czech',                   'native_name' => 'Čeština',               'native_short' => 'Čeština',   'short_code' => 'CS'],
        'el'    => ['code' => 'el',    'name' => 'Greek',                   'native_name' => 'Ελληνικά',              'native_short' => 'Ελληνικά',  'short_code' => 'EL'],
        'tr'    => ['code' => 'tr',    'name' => 'Turkish',                 'native_name' => 'Türkçe',                'native_short' => 'Türkçe',    'short_code' => 'TR'],
        'uk'    => ['code' => 'uk',    'name' => 'Ukrainian',               'native_name' => 'Українська',            'native_short' => 'Українська','short_code' => 'UK'],
        'he'    => ['code' => 'he',    'name' => 'Hebrew',                  'native_name' => 'עברית',                 'native_short' => 'עברית',     'short_code' => 'HE'],
        'vi'    => ['code' => 'vi',    'name' => 'Vietnamese',              'native_name' => 'Tiếng Việt',            'native_short' => 'Tiếng Việt','short_code' => 'VI'],
        'id'    => ['code' => 'id',    'name' => 'Indonesian',              'native_name' => 'Bahasa Indonesia',      'native_short' => 'Bahasa Indonesia', 'short_code' => 'ID'],
        'eo'    => ['code' => 'eo',    'name' => 'Esperanto',               'native_name' => 'Esperanto',             'native_short' => 'Esperanto', 'short_code' => 'EO'],
    ];

    /**
     * Returns the full catalog of supported ISO languages.
     *
     * @return array<string, array{code: string, name: string, native_name: string, native_short: string, short_code: string}>
     */
    public static function getAll(): array
    {
        return self::$catalog;
    }

    /**
     * Resolves metadata for a language code (e.g. 'pt-br', 'en_US', 'es').
     *
     * @param string $code Language tag or code.
     * @return array{code: string, name: string, native_name: string, native_short: string, short_code: string}|null
     */
    public static function get(string $code): ?array
    {
        $normalized = self::normalizeCode($code);
        if (isset(self::$catalog[$normalized])) {
            return self::$catalog[$normalized];
        }

        // Try case-insensitive lookup
        foreach (self::$catalog as $entryCode => $entry) {
            if (strcasecmp($entryCode, $normalized) === 0) {
                return $entry;
            }
        }

        // Try base 2-letter language code fallback (e.g. 'pt-AO' -> 'pt')
        $base = explode('-', $normalized)[0];
        if (isset(self::$catalog[$base])) {
            return [
                'code'         => $code,
                'name'         => self::$catalog[$base]['name'] . ' (' . strtoupper($code) . ')',
                'native_name'  => self::$catalog[$base]['native_name'],
                'native_short' => self::$catalog[$base]['native_short'],
                'short_code'   => strtoupper($base),
            ];
        }

        return null;
    }

    /**
     * Returns the short 2-letter uppercase representation of a language code.
     * Example: 'pt-BR' -> 'PT', 'en_US' -> 'EN'.
     *
     * @param string $code
     * @return string
     */
    public static function getShortCode(string $code): string
    {
        $entry = self::get($code);
        if ($entry !== null) {
            return $entry['short_code'];
        }

        $clean = str_replace('_', '-', trim($code));
        $base = explode('-', $clean)[0];
        return strtoupper($base !== '' ? $base : $code);
    }

    /**
     * Returns the full uppercase BCP-47 representation of a language code.
     * Example: 'pt-br' -> 'PT-BR', 'en_US' -> 'EN-US', 'es' -> 'ES'.
     *
     * @param string $code
     * @return string
     */
    public static function getLongCode(string $code): string
    {
        $clean = str_replace('_', '-', trim($code));
        return strtoupper($clean);
    }

    /**
     * Returns the full native endonym for a language code (with regional variant if applicable).
     * Example: 'pt-BR' -> 'Português (Brasil)', 'en' -> 'English'.
     *
     * @param string $code
     * @return string
     */
    public static function getNativeName(string $code): string
    {
        $entry = self::get($code);
        if ($entry !== null) {
            return $entry['native_name'];
        }

        return $code;
    }

    /**
     * Returns the short native endonym for a language code (omitting regional suffixes in parentheses).
     * Example: 'pt-BR' -> 'Português', 'zh-CN' -> '中文', 'en' -> 'English'.
     *
     * @param string $code
     * @return string
     */
    public static function getNativeShortName(string $code): string
    {
        $entry = self::get($code);
        if ($entry !== null && !empty($entry['native_short'])) {
            return $entry['native_short'];
        }

        $native = self::getNativeName($code);
        $stripped = (string) preg_replace('/\s*\(.*?\)/', '', $native);
        $clean = trim($stripped);
        return $clean !== '' ? $clean : $native;
    }

    /**
     * Formats a language label according to the specified display mode.
     *
     * Supported modes:
     * - 'native_short' (default, alias 'native'): Short endonym without regional parenthetical (e.g. 'Português')
     * - 'native_long': Full endonym with regional distinction (e.g. 'Português (Brasil)')
     * - 'code_short' (alias 'short'): 2-letter uppercase ISO 639-1 code (e.g. 'PT')
     * - 'code_long': Full uppercase BCP-47 code (e.g. 'PT-BR')
     *
     * @param string $code Language code (e.g. 'pt-BR', 'en').
     * @param string $mode Display mode ('native_short'/'native', 'native_long', 'code_short'/'short', 'code_long').
     * @return string Formatted label.
     */
    public static function getLabel(string $code, string $mode = 'native_short'): string
    {
        switch ($mode) {
            case 'code_short':
            case 'short':
                return self::getShortCode($code);
            case 'code_long':
                return self::getLongCode($code);
            case 'native_long':
                return self::getNativeName($code);
            case 'native_short':
            case 'native':
            default:
                return self::getNativeShortName($code);
        }
    }

    /**
     * Normalizes language code replacing underscores with hyphens (e.g. 'pt_BR' -> 'pt-BR').
     *
     * @param string $code
     * @return string
     */
    public static function normalizeCode(string $code): string
    {
        $code = str_replace('_', '-', trim($code));
        $parts = explode('-', $code);
        if (count($parts) === 2) {
            return strtolower($parts[0]) . '-' . strtoupper($parts[1]);
        }
        return strtolower($code);
    }
}
