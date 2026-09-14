<?php

declare(strict_types=1);

namespace Indieinabox\Support;

/**
 * Class TextParser
 *
 * Provides string normalization, ASCII transliteration, slug generation,
 * array access utilities, and hashtag extraction.
 */
class TextParser
{
    /**
     * Safely retrieves a key from an array with a fallback default.
     *
     * @param array<string, mixed> $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function arrayGet(array $array, string $key, mixed $default = null): mixed
    {
        return $array[$key] ?? $default;
    }

    /**
     * Extracts hashtags from a given text string.
     *
     * @param string $text The post text
     * @return array<string> List of unique hashtags without the # symbol
     */
    public static function extractHashtags(string $text): array
    {
        $hashtags = [];
        if (preg_match_all('/(?:^|\s)#([\w\x{00C0}-\x{FFFF}]+)/u', $text, $matches)) {
            foreach ($matches[1] as $tag) {
                if (!is_numeric($tag)) {
                    $hashtags[] = mb_strtolower(trim($tag));
                }
            }
        }
        return array_unique($hashtags);
    }

    /**
     * Removes accents from a string using iconv and transliteration.
     *
     * @param string $string
     * @return string
     */
    public static function unaccent(string $string): string
    {
        if (!preg_match('/[\x80-\xff]/', $string)) {
            return $string;
        }

        $custom = [
            'Ä' => 'Ae', 'ä' => 'ae', 'Ö' => 'Oe', 'ö' => 'oe',
            'Ü' => 'Ue', 'ü' => 'ue', 'ß' => 'ss', 'Æ' => 'Ae', 'æ' => 'ae'
        ];
        $string = str_replace(array_keys($custom), array_values($custom), $string);

        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        return $transliterated !== false ? $transliterated : $string;
    }

    /**
     * Converts a UTF-8 string to ASCII.
     *
     * @param string $str
     * @param string $unknown
     * @return string
     */
    public static function utf8ToAscii(string $str, string $unknown = '?'): string
    {
        static $utf8ToAscii = [];

        if (strlen($str) === 0) {
            return '';
        }

        preg_match_all('/.{1}|[^\x00]{1,1}$/us', $str, $ar);
        $chars = $ar[0];

        foreach ($chars as $i => $c) {
            $byte = ord($c[0]);

            if ($byte <= 127) {
                continue;
            }

            if ($byte >= 254) {
                $chars[$i] = $unknown;
                continue;
            }

            $ord = self::decodeUtf8Codepoint($c);
            $bank = $ord >> 8;

            self::loadUtf8Bank($bank, $utf8ToAscii);

            $newchar = $ord & 255;
            $chars[$i] = array_key_exists($newchar, $utf8ToAscii[$bank])
                ? $utf8ToAscii[$bank][$newchar]
                : $unknown;
        }

        return implode('', $chars);
    }

    /**
     * Decodes a multi-byte UTF-8 character sequence into its Unicode codepoint.
     *
     * @param string $c
     * @return int
     */
    private static function decodeUtf8Codepoint(string $c): int
    {
        $b0 = ord($c[0]);

        if ($b0 >= 252) {
            return ($b0 - 252) * 1073741824
                + (ord($c[1]) - 128) * 16777216
                + (ord($c[2]) - 128) * 262144
                + (ord($c[3]) - 128) * 4096
                + (ord($c[4]) - 128) * 64
                + (ord($c[5]) - 128);
        }

        if ($b0 >= 248) {
            return ($b0 - 248) * 16777216
                + (ord($c[1]) - 128) * 262144
                + (ord($c[2]) - 128) * 4096
                + (ord($c[3]) - 128) * 64
                + (ord($c[4]) - 128);
        }

        if ($b0 >= 240) {
            return ($b0 - 240) * 262144
                + (ord($c[1]) - 128) * 4096
                + (ord($c[2]) - 128) * 64
                + (ord($c[3]) - 128);
        }

        if ($b0 >= 224) {
            return ($b0 - 224) * 4096
                + (ord($c[1]) - 128) * 64
                + (ord($c[2]) - 128);
        }

        return ($b0 - 192) * 64 + (ord($c[1]) - 128);
    }

    /**
     * Lazily loads a UTF-8 translation bank.
     *
     * @param int $bank
     * @param array<int, array<int, string>> $cache
     * @return void
     */
    private static function loadUtf8Bank(int $bank, array &$cache): void
    {
        if (array_key_exists($bank, $cache)) {
            return;
        }

        $cache[$bank] = [];
    }

    /**
     * Converts a string into a clean, URL-safe slug.
     *
     * @param string $str
     * @return string
     */
    public static function slugize(string $str): string
    {
        $str = urldecode($str);
        $str = str_replace(' ', '-', trim($str));
        $str = self::unaccent($str);
        $str = strtolower($str);
        $str = (string) preg_replace('/[^a-z0-9-]/', '', $str);
        return trim($str);
    }
}
