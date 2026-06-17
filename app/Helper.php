<?php

declare(strict_types=1);

namespace Indieinabox;

class Helper
{
    /**
     * Helper function to get a value from nested array with default
     *
     * @param  array<string, mixed>  $array
     * @param  string $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function arrayGet(array $array, string $key, $default = null)
    {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    /**
     * Helper function to determine the kind of content
     *
     * @param  Page|array<string, mixed> $page
     * @return array{localized: string, kind: string}
     */
    public static function kind($page): array
    {
        global $site, $kindspath;
        $isObject = $page instanceof Page;
        $pageKind = $isObject ? $page->kind : ($page["kind"] ?? null);
        $pageSlug = $isObject ? $page->slug : ($page["slug"] ?? "");
        $pageLang = $isObject ? $page->lang : ($page["lang"] ?? "en");

        if ($pageKind !== null && $pageKind !== "") {
            $kind = $pageKind;
            $localizedkind = $pageKind;
        } else {
            $localizedkind = explode("/", $pageSlug);
            if ($pageLang == $site->defaultlang) {
                $localizedkind = $localizedkind[0];
            } else {
                $localizedkind = $localizedkind[1];
            }
            foreach ($kindspath as $key => $value) {
                if (in_array($localizedkind, $value)) {
                    $kind = $key;
                    break;
                }
            }
            if (!isset($kind)) {
                $kind = "generic";
                $localizedkind = "generic";
            }
        }
        return [
            "localized" => $localizedkind,
            "kind" => $kind,
        ];
    }

    /**
     * Helper function to format dates
     *
     * @param  Page|array<string, mixed> $page
     * @return array{long: string, iso: string}
     */
    public static function localizeddate($page): array
    {
        global $originaldaysofweek, $originalmonths, $intl;
        setlocale(LC_TIME, 'en-us');

        if ($page instanceof Page) {
            $epoch = $page->date;
            $lang = $page->lang;
        } else {
            $epoch = $page["date"] ?? time();
            $lang = $page["lang"] ?? "en";
        }

        if ($epoch instanceof \DateTime) {
            $date = $epoch;
        } else {
            if (is_float($epoch)) {
                $epoch = intval($epoch);
            }
            if (is_int($epoch) || (is_string($epoch) && is_numeric($epoch))) {
                $epoch = strval($epoch);
                $date = \DateTime::createFromFormat("U", $epoch);
            } else {
                $date = new \DateTime((string)$epoch);
            }
        }

        $date->setTimezone(new \DateTimeZone("America/Sao_Paulo"));
        $isoformat = date_format($date, 'c');
        $longformat = date_format($date, $intl[$lang]["localizeddate"]["full"]);
        // Change America/Sao_Paulo to short timezone
        $longformat = str_replace("America/Sao_Paulo", ($date->format('I') == '1') ? 'BRST' : 'BRT', $longformat);
        $longformat = str_replace($originaldaysofweek, $intl[$lang]["localizeddate"]["daysofweek"], $longformat);
        $longformat = str_replace($originalmonths, $intl[$lang]["localizeddate"]["months"], $longformat);
        return [
            "long" => $longformat,
            "iso" => $isoformat
        ];
    }

    /**
     * Remove accents from a string
     *
     * @param  string $string
     * @return string
     */
    public static function unaccent(string $string): string
    {
        if (!preg_match('/[\x80-\xff]/', $string)) {
            return $string;
        }

        static $chars = null;
        if ($chars === null) {
            $path = dirname(__DIR__) . '/data/chars.php';
            if (file_exists($path)) {
                $chars = require $path;
            } else {
                throw new \RuntimeException("chars.php not found");
            }
        }

        return strtr($string, $chars);
    }

    /**
     * Convert UTF-8 string to ASCII
     *
     * @param  string $str
     * @param  string $unknown
     * @return string
     */
    public static function utf8ToAscii(string $str, string $unknown = '?'): string
    {
        static $UTF8_TO_ASCII = [];

        if (strlen($str) == 0) {
            return '';
        }

        preg_match_all('/.{1}|[^\x00]{1,1}$/us', $str, $ar);
        $chars = $ar[0];

        foreach ($chars as $i => $c) {
            $byte = ord($c[0]);

            if ($byte <= 127) {
                continue; // ASCII - next please
            }

            if ($byte >= 254) {
                $chars[$i] = $unknown;
                continue; // error
            }

            $ord  = self::decodeUtf8Codepoint($c);
            $bank = $ord >> 8;

            self::loadUtf8Bank($bank, $UTF8_TO_ASCII);

            $newchar = $ord & 255;
            $chars[$i] = array_key_exists($newchar, $UTF8_TO_ASCII[$bank])
                ? $UTF8_TO_ASCII[$bank][$newchar]
                : $unknown;
        }

        return implode('', $chars);
    }

    /**
     * Decode a multi-byte UTF-8 character sequence into its Unicode codepoint.
     *
     * @param  string $c  Raw multi-byte character (up to 6 bytes)
     * @return int        Unicode codepoint value
     */
    private static function decodeUtf8Codepoint(string $c): int
    {
        $b0 = ord($c[0]);

        if ($b0 >= 252) {
            // 6-byte sequence (U+4000000 – U+7FFFFFFF)
            return ($b0 - 252) * 1073741824
                + (ord($c[1]) - 128) * 16777216
                + (ord($c[2]) - 128) * 262144
                + (ord($c[3]) - 128) * 4096
                + (ord($c[4]) - 128) * 64
                + (ord($c[5]) - 128);
        }

        if ($b0 >= 248) {
            // 5-byte sequence (U+200000 – U+3FFFFFF)
            return ($b0 - 248) * 16777216
                + (ord($c[1]) - 128) * 262144
                + (ord($c[2]) - 128) * 4096
                + (ord($c[3]) - 128) * 64
                + (ord($c[4]) - 128);
        }

        if ($b0 >= 240) {
            // 4-byte sequence (U+10000 – U+1FFFFF)
            return ($b0 - 240) * 262144
                + (ord($c[1]) - 128) * 4096
                + (ord($c[2]) - 128) * 64
                + (ord($c[3]) - 128);
        }

        if ($b0 >= 224) {
            // 3-byte sequence (U+800 – U+FFFF)
            return ($b0 - 224) * 4096
                + (ord($c[1]) - 128) * 64
                + (ord($c[2]) - 128);
        }

        // 2-byte sequence (U+80 – U+7FF)
        return ($b0 - 192) * 64 + (ord($c[1]) - 128);
    }

    /**
     * Lazily load a UTF-8 translation bank from disk into the static cache.
     *
     * @param  int                      $bank         Bank index (high byte of codepoint)
     * @param  array<int, array<int, string>> &$cache Reference to the static lookup table
     * @return void
     */
    private static function loadUtf8Bank(int $bank, array &$cache): void
    {
        if (array_key_exists($bank, $cache)) {
            return;
        }

        $path = dirname(__DIR__) . '/data/' . sprintf('x%02x', $bank) . '.php';
        if (file_exists($path)) {
            include $path;
        } else {
            $cache[$bank] = [];
        }
    }

    /**
     * Slugize a string
     *
     * @param  string $str
     * @return string
     */
    public static function slugize(string $str): string
    {
        $str = urldecode($str);
        $str = str_replace(' ', '-', trim($str));
        $str = self::unaccent($str);
        $str = strtolower($str);
        //Remove everything that is not a letter, number or dash
        $str = (string)preg_replace('/[^a-z0-9-]/', '', $str);
        $str = trim($str);
        return $str;
    }

    /**
     * Sorts pages by date descending
     *
     * @param  array<int, array<string, mixed>|Page> $pages
     * @return array<int, array<string, mixed>|Page>
     */
    public static function sortByDate(array $pages): array
    {
        usort(
            $pages,
            function ($a, $b) {
                if (!isset($a["date"])) {
                    $a["date"] = -1;
                }

                if (!isset($b["date"])) {
                    $b["date"] = -1;
                }

                return $b["date"] - $a["date"];
            }
        );

        return $pages;
    }

    /**
     * Recursively sorts an array by keys
     *
     * @param  array<string, mixed> $array
     * @return void
     */
    public static function recursive_ksort(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                self::recursive_ksort($value);
            }
        }
        ksort($array, SORT_STRING | SORT_FLAG_CASE);
    }

    /**
     * Get directory contents recursively
     *
     * @param  string $dir
     * @param  array<int, string> $results
     * @return array<int, string>
     */
    public static function getDirContents(string $dir, array &$results = []): array
    {
        $files = scandir($dir);

        foreach ($files as $key => $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if ($path !== false) {
                if (!is_dir($path)) {
                    $results[] = $path;
                } elseif ($value != "." && $value != "..") {
                    self::getDirContents($path, $results);
                    $results[] = $path;
                }
            }
        }

        return $results;
    }

    /**
     * Get original content slug translation
     *
     * @param  string $slug
     * @param  string $lang
     * @return string
     */
    public static function getoriginalcontent(string $slug, string $lang): string
    {
        global $urltranslations;
        if (is_array($urltranslations)) {
            foreach ($urltranslations as $key => $val) {
                if (isset($val[$lang]) && stripos($val[$lang], $slug) !== false) {
                    return $key;
                }
            }
        }
        return "";
    }

    /**
     * Beautify HTML content
     *
     * @param  string $html
     * @return string
     */
    public static function beautifyhtml(string $html): string
    {
        if (empty($html)) {
            return "";
        }
        $beautify = new \Beautify_Html(
            array(
            'indent_inner_html' => false,
            'indent_char' => " ",
            'indent_size' => 2,
            'wrap_line_length' => 32786,
            'unformatted' => ['code', 'pre'],
            'preserve_newlines' => false,
            'max_preserve_newlines' => 32786,
            'indent_scripts'    => 'normal', // keep|separate|normal
            )
        );
        return $beautify->beautify($html);
    }

    /**
     * Minify HTML content
     *
     * @param  string $html
     * @return string
     */
    public static function minifyhtml(string $html): string
    {
        if (empty($html)) {
            return "";
        }
        $minifier = new \Indieinabox\HtmlMinifier(
            [
            'collapse_whitespace' => true,
            'disable_comments' => true,
            ]
        );
        return $minifier->minify($html);
    }

    /**
     * Recursively remove directory
     *
     * @param  string $dir
     * @param  bool $keepRootDir
     * @return bool
     */
    public static function recursive_rmdir(string $dir, bool $keepRootDir = false): bool
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            throw new \RuntimeException("'$dir' is not a directory");
        }

        $dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;

        try {
            $items = new \DirectoryIterator($dir);

            foreach ($items as $item) {
                if ($item->isDot()) {
                    continue;
                }

                $path = $item->getPathname();

                if ($item->isDir()) {
                    if (!self::recursive_rmdir($path)) {
                        return false;
                    }
                } else {
                    if (!unlink($path)) {
                        throw new \RuntimeException("Failed to delete file: $path");
                    }
                }
            }

            if (!$keepRootDir && !rmdir($dir)) {
                throw new \RuntimeException("Failed to remove directory: $dir");
            }

            return true;
        } catch (\Exception $e) {
            throw new \RuntimeException("Error while removing directory: " . $e->getMessage());
        }
    }

    /**
     * Translation lookup
     *
     * @param  string $text
     * @param  string|null $lang
     * @return string
     */
    public static function translate(string $text, ?string $lang = null): string
    {
        global $translations, $page, $p, $site;
        if ($lang == null) {
            if (isset($p)) {
                $lang = $p instanceof Page ? $p->lang : ($p["lang"] ?? "en");
            } elseif (isset($page)) {
                $lang = $page instanceof Page ? $page->lang : ($page["lang"] ?? "en");
            } else {
                $lang = "en";
            }
        }
        if ($lang == $site->localization->defaultLang) {
            return $text;
        }
        if (isset($translations[$lang])) {
            foreach ($translations[$lang] as $o => $v) {
                if (mb_stripos($o, $text) !== false && !empty($v)) {
                    $found = $o;
                    break;
                }
            }
        }
        if (!isset($found) || empty($found)) {
            $translations[$lang][$text] = '';
            self::updateTranslations();
            return $text;
        }
        return $translations[$lang][$found];
    }

    /**
     * Translate and make lowercase
     *
     * @param  string $text
     * @return string
     */
    public static function translateLowercase(string $text): string
    {
        return strtolower(self::translate($text));
    }

    /**
     * Translate and slugize
     *
     * @param  string $text
     * @return string
     */
    public static function translateSlugize(string $text): string
    {
        return self::slugize(self::translate($text));
    }

    /**
     * Update translations file
     *
     * @return void
     */
    public static function updateTranslations(): void
    {
        global $translations, $site;
        $file = $site->paths->baseDir . DIRECTORY_SEPARATOR . "data/translations.php";
        self::recursive_ksort($translations);
        file_put_contents(
            $file,
            "<?php\nglobal \$translations;\n\$translations= "
                . var_export($translations, true)
                . ";\n?>"
        );
    }

    /**
     * List posts, sorting by date descending, up to 10 posts
     *
     * @return string
     */
    public static function listposts(): string
    {
        global $base, $pages, $site;
        $localpages = $pages instanceof Pages ? $pages->all() : $pages;
        $localpages = array_filter($localpages, [self::class, 'removegeneric']);
        usort(
            $localpages,
            function ($a, $b) {
                $dateA = $a instanceof Page ? $a->date : ($a['date'] ?? 0);
                $dateB = $b instanceof Page ? $b->date : ($b['date'] ?? 0);
                $timeA = $dateA instanceof \DateTime ? $dateA->getTimestamp() : $dateA;
                $timeB = $dateB instanceof \DateTime ? $dateB->getTimestamp() : $dateB;
                return $timeB <=> $timeA;
            }
        );
        $count = 0;
        ob_start();
        foreach ($localpages as $page) {
            include $base . DIRECTORY_SEPARATOR . "resources/views/includes/summary.php";
            $count++;
            if ($count >= 10) {
                break;
            }
        }
        return (string)ob_get_clean();
    }

    /**
     * Remove generic/page items from filter list
     *
     * @param  mixed $var
     * @return bool
     */
    public static function removegeneric($var): bool
    {
        $kind = $var instanceof Page ? $var->kind : ($var["kind"] ?? null);
        if ($kind !== null) {
            if ($kind !== "generic" && $kind !== "page") {
                return true;
            }
        }
        return false;
    }
}

