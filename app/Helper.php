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
     * Get the configuration for a specific kind with fallbacks.
     */
    public static function getKindConfig(string $kind): array
    {
        global $site;
        $kind = strtolower($kind);
        $config = $site->config['kinds'][$kind] ?? null;

        if (!$config) {
            static $warned = [];
            if (!isset($warned[$kind]) && !in_array($kind, ['generic', 'page', 'home'])) {
                if (php_sapi_name() === 'cli') {
                    echo "[WARNING] Missing config for kind '{$kind}'. Using defaults.\n";
                }
                $warned[$kind] = true;
            }
            $config = [];
        }

        return array_merge([
            'content_dir' => $kind,
            'title' => [],
            'palette' => null,
            'has_title' => true,
            'show_on_home' => false,
            'display_mode' => 'default'
        ], $config);
    }

    /**
     * Helper function to determine the kind of content
     *
     * @param  Page|array<string, mixed> $page
     * @return array{localized: string, kind: string}
     */
    public static function kind($page): array
    {
        global $site;
        $isObject = $page instanceof Page;
        $pageKind = $isObject ? $page->kind : ($page["kind"] ?? null);
        $pageSlug = $isObject ? $page->slug : ($page["slug"] ?? "");
        $pageLang = $isObject ? $page->lang : ($page["lang"] ?? "en");

        if ($pageKind !== null && $pageKind !== "") {
            $kind = $pageKind;
            $kindConfig = $site->config['kinds'][$kind] ?? null;
            if ($kindConfig) {
                $localizedkind = self::getKindFolder($kind, $pageLang);
            } else {
                $localizedkind = $kind;
            }
        } else {
            $localizedkindSegment = explode("/", $pageSlug);
            if ($pageLang == $site->defaultlang) {
                $localizedkindSegment = $localizedkindSegment[0];
            } else {
                $localizedkindSegment = $localizedkindSegment[1] ?? $localizedkindSegment[0];
            }
            
            // Resolve kind from config content_dir
            if (!empty($site->config['kinds'])) {
                foreach ($site->config['kinds'] as $k => $conf) {
                    $cDir = $conf['content_dir'] ?? $k;
                    if ($cDir === $localizedkindSegment) {
                        $kind = $k;
                        break;
                    }
                }
            }
            
            // Fallback to legacy kindspath if not found
            if (!isset($kind)) {
                global $kindspath;
                if ($kindspath === null) {
                    $kindspath = \Indieinabox\Database::getSetting('kindspath', []);
                }
                if (!empty($kindspath)) {
                    foreach ($kindspath as $key => $value) {
                        if (in_array($localizedkindSegment, $value)) {
                            $kind = $key;
                            break;
                        }
                    }
                }
            }

            if (!isset($kind)) {
                $kind = "generic";
                $localizedkind = "generic";
            } else {
                $kindConfig = $site->config['kinds'][$kind] ?? null;
                if ($kindConfig) {
                    $localizedkind = self::getKindFolder($kind, $pageLang);
                } else {
                    $localizedkind = $localizedkindSegment;
                }
            }
        }
        return [
            "localized" => $localizedkind,
            "kind" => $kind,
        ];
    }

    /**
     * Get the localized folder name for a specific kind and language.
     *
     * @param string $kind
     * @param string $lang
     * @return string
     */
    public static function getKindFolder(string $kind, string $lang): string
    {
        $config = self::getKindConfig($kind);

        // If it's a special system kind (generic, page, home) and has no config, just return the kind itself
        if (in_array($kind, ['generic', 'page', 'home']) && empty($config['title'])) {
            return $kind;
        }

        // 1. If content_dir is defined as an array mapped by language, use that
        if (isset($config['content_dir']) && is_array($config['content_dir'])) {
            $folder = $config['content_dir'][$lang] ?? reset($config['content_dir']);
            if ($folder) {
                return self::slugize((string)$folder);
            }
        }

        // 2. Otherwise, check if we have a title translation for this language
        if (!empty($config['title']) && is_array($config['title'])) {
            if (isset($config['title'][$lang])) {
                return self::slugize((string)$config['title'][$lang]);
            }
            // Fallback to defaultlang or first title
            global $site;
            $defaultLang = $site->localization->defaultLang ?? 'en';
            if (isset($config['title'][$defaultLang])) {
                return self::slugize((string)$config['title'][$defaultLang]);
            }
            if (isset($config['title']['en'])) {
                return self::slugize((string)$config['title']['en']);
            }
            $first = reset($config['title']);
            if ($first) {
                return self::slugize((string)$first);
            }
        }

        // 3. Fallback to kind's content_dir if it's a string, or the kind itself
        $contentDir = $config['content_dir'] ?? $kind;
        return self::slugize((string)$contentDir);
    }

    /**
     * Return a human-readable, localized display label for a post kind.
     *
     * Maps internal slugs (article, photo, note, jardim, etc.) to their
     * translated labels defined in config.yml.
     *
     * @param  string      $kind Internal kind slug
     * @param  string|null $lang Target language (defaults to current page lang)
     * @return string
     */
    public static function kindLabel(string $kind, ?string $lang = null): string
    {
        global $site;
        $config = self::getKindConfig($kind);
        $targetLang = $lang ?? $site->localization->defaultLang ?? 'en';
        
        if (!empty($config['title']) && is_array($config['title'])) {
            if (isset($config['title'][$targetLang])) {
                return $config['title'][$targetLang];
            }
            // Fallback to english or the first available translation
            if (isset($config['title']['en'])) {
                return $config['title']['en'];
            }
            return reset($config['title']);
        }
        
        // Hard fallback to ucfirst of the kind
        return ucfirst($kind);
    }

    /**
     * Return a hyperlinked, human-readable display label for a post kind.
     * The link points to the kind's index page in the corresponding language.
     *
     * @param  Page   $page The page context where this link is rendered
     * @param  string $kind Internal kind slug
     * @return string
     */
    public static function kindLink(Page $page, string $kind): string
    {
        global $site;
        $lang = $page->lang ?? $site->localization->defaultLang ?? 'en';
        $defaultLang = $site->localization->defaultLang ?? 'en';
        $prettylinks = $site->options->prettylinks ?? true;
        
        $label = self::kindLabel($kind, $lang);
        
        // If it's a generic kind, don't link it
        if (in_array($kind, ['generic', 'home', 'page'], true)) {
            return '[' . strtoupper(htmlspecialchars($label)) . ']';
        }
        
        $folder = self::getKindFolder($kind, $lang);
        $langPrefix = ($lang === $defaultLang) ? '' : $lang . '/';
        
        if ($prettylinks) {
            $url = ltrim($langPrefix . $folder . '/', '/');
        } else {
            $url = ltrim($langPrefix . $folder . '.html', '/');
        }
        
        $relUrl = $page->relpath . $url;
        
        return '<a href="' . $relUrl . '">[' . strtoupper(htmlspecialchars($label)) . ']</a>';
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
        if ($intl === null) {
            $intl = \Indieinabox\Database::getSetting('intl', []);
        }
        if ($originaldaysofweek === null) {
            $originaldaysofweek = \Indieinabox\Database::getSetting('originaldaysofweek', []);
        }
        if ($originalmonths === null) {
            $originalmonths = \Indieinabox\Database::getSetting('originalmonths', []);
        }
        setlocale(LC_TIME, 'en-us');

        if ($page instanceof Page) {
            $epoch = $page->date;
            $lang = $page->lang;
        } else {
            $epoch = $page["date"] ?? time();
            $lang = $page["lang"] ?? "en";
        }

        if (!isset($intl[$lang])) {
            if (($lang === 'pt' || str_starts_with($lang, 'pt-')) && isset($intl['pt-br'])) {
                $lang = 'pt-br';
            } elseif (($lang === 'es' || str_starts_with($lang, 'es-')) && isset($intl['es'])) {
                $lang = 'es';
            } else {
                $lang = 'en';
            }
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

        // Custom transliteration rules that iconv typically misses or handles differently depending on locale
        $custom = ['Ä' => 'Ae', 'ä' => 'ae', 'Ö' => 'Oe', 'ö' => 'oe', 'Ü' => 'Ue', 'ü' => 'ue', 'ß' => 'ss', 'Æ' => 'Ae', 'æ' => 'ae'];
        $string = str_replace(array_keys($custom), array_values($custom), $string);

        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        return $transliterated !== false ? $transliterated : $string;
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

        $cache[$bank] = [];
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
        if ($translations === null) {
            $translations = \Indieinabox\Database::getTranslations();
        }

        if ($lang == null) {
            if (isset($p)) {
                $lang = $p instanceof Page ? $p->lang : ($p["lang"] ?? "en");
            } elseif (isset($page)) {
                $lang = $page instanceof Page ? $page->lang : ($page["lang"] ?? "en");
            } else {
                $lang = "en";
            }
        }

        // 1. Try config-based translations first
        if ($site && !empty($site->config['translations'])) {
            foreach ($site->config['translations'] as $original => $langs) {
                if (strcasecmp($original, $text) === 0) {
                    if (isset($langs[$lang]) && $langs[$lang] !== '') {
                        return $langs[$lang];
                    }
                }
            }
        }

        if ($lang == $site->localization->defaultLang) {
            return $text;
        }

        // 2. Fallback to translations.php array
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
        global $translations;
        self::recursive_ksort($translations);
        $db = \Indieinabox\Database::getDb();
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
                        $ins = $db->prepare('INSERT INTO translations (lang, phrase_key, phrase_value) VALUES (:lang, :key, :val)');
                        $ins->bindValue(':lang', $lang);
                        $ins->bindValue(':key', $key);
                        $ins->bindValue(':val', $val);
                        $ins->execute();
                    }
                }
            }
        }
    }

    /**
     * List posts, sorting by date descending, up to 10 posts
     *
     * @return string
     */
    public static function listposts(): string
    {
        global $pages, $site, $p;
        $currentLang = $p instanceof Page ? $p->lang : ($p['lang'] ?? ($site->localization->defaultLang ?? 'en'));
        $base = $site->paths->baseDir;
        $localpages = $pages instanceof Pages ? $pages->all() : $pages;
        $localpages = array_filter($localpages, [self::class, 'removegeneric']);
        $localpages = array_filter($localpages, function($page) use ($currentLang) {
            $lang = $page instanceof Page ? $page->lang : ($page['lang'] ?? 'en');
            return $lang === $currentLang;
        });
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
        $themeDir = $site->paths->themeDir ?? 'theme';
        foreach ($localpages as $page) {
            ThemeManager::loadView($base . DIRECTORY_SEPARATOR . $themeDir . DIRECTORY_SEPARATOR . "views/includes/summary.php", get_defined_vars());
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
            $config = self::getKindConfig($kind);
            return !empty($config['show_on_home']);
        }
        return false;
    }
    /**
     * Create a small thumbnail using GD and the global palette.
     *
     * @param string $caminhoOriginal
     * @param string $caminhoDestino
     * @param int $tamanhoFocal
     * @param array $corBG
     * @param array $corFG
     * @return bool
     */
    public static function createThumbnail(
        string $caminhoOriginal,
        string $caminhoDestino,
        int $tamanhoFocal,
        array $corBG,
        array $corFG
    ): bool {
        if (!is_dir(dirname($caminhoDestino))) {
            mkdir(dirname($caminhoDestino), 0777, true);
        }

        $ext = strtolower(pathinfo($caminhoOriginal, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $imgOriginal = @imagecreatefrompng($caminhoOriginal);
        } elseif ($ext === 'gif') {
            $imgOriginal = @imagecreatefromgif($caminhoOriginal);
        } elseif ($ext === 'webp') {
            $imgOriginal = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($caminhoOriginal) : false;
        } else {
            $imgOriginal = @imagecreatefromjpeg($caminhoOriginal);
            if ($imgOriginal && function_exists('exif_read_data')) {
                $exif = @exif_read_data($caminhoOriginal);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3: $imgOriginal = imagerotate($imgOriginal, 180, 0); break;
                        case 6: $imgOriginal = imagerotate($imgOriginal, -90, 0); break;
                        case 8: $imgOriginal = imagerotate($imgOriginal, 90, 0); break;
                    }
                }
            }
        }

        if (!$imgOriginal) {
            return false;
        }

        $larguraOrig = imagesx($imgOriginal);
        $alturaOrig = imagesy($imgOriginal);
        
        $srcX = 0;
        $srcY = 0;
        
        if ($larguraOrig > $alturaOrig) {
            $srcX = ($larguraOrig - $alturaOrig) / 2;
            $larguraOrig = $alturaOrig;
        } else {
            $srcY = ($alturaOrig - $larguraOrig) / 2;
            $alturaOrig = $larguraOrig;
        }

        $imgRedimensionada = imagecreatetruecolor($tamanhoFocal, $tamanhoFocal);
        imagecopyresampled($imgRedimensionada, $imgOriginal, 0, 0, $srcX, $srcY, $tamanhoFocal, $tamanhoFocal, $larguraOrig, $alturaOrig);
        imagedestroy($imgOriginal);

        $imgFinal = imagecreate($tamanhoFocal, $tamanhoFocal);
        $alocadaBG = imagecolorallocate($imgFinal, $corBG[0], $corBG[1], $corBG[2]);
        $alocadaFG = imagecolorallocate($imgFinal, $corFG[0], $corFG[1], $corFG[2]);

        for ($y = 0; $y < $tamanhoFocal; $y++) {
            for ($x = 0; $x < $tamanhoFocal; $x++) {
                $rgb = imagecolorat($imgRedimensionada, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $luminosidade = ($r * 0.299 + $g * 0.587 + $b * 0.114);
                
                $cor = ($luminosidade > 128) ? $alocadaBG : $alocadaFG;
                imagesetpixel($imgFinal, $x, $y, $cor);
            }
        }
        imagedestroy($imgRedimensionada);

        $result = imagegif($imgFinal, $caminhoDestino);
        imagedestroy($imgFinal);

        return $result;
    }


    /**
     * Atkinson adaptive dithering using GD to index 8-bit GIF
     *
     * @param string $caminhoOriginal
     * @param string $caminhoDestino
     * @param int $larguraFocal
     * @param array $corBG
     * @param array $corFG
     * @param bool $aplicarAutomacao
     * @return bool
     */
    public static function ditherImageToGif(
        string $caminhoOriginal,
        string $caminhoDestino,
        int $larguraFocal,
        array $corBG,
        array $corFG,
        bool $aplicarAutomacao = true
    ): bool {
        if (!is_dir(dirname($caminhoDestino))) {
            mkdir(dirname($caminhoDestino), 0777, true);
        }

        $ext = strtolower(pathinfo($caminhoOriginal, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $imgOriginal = @imagecreatefrompng($caminhoOriginal);
        } elseif ($ext === 'gif') {
            $imgOriginal = @imagecreatefromgif($caminhoOriginal);
        } elseif ($ext === 'webp') {
            $imgOriginal = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($caminhoOriginal) : false;
        } else {
            $imgOriginal = @imagecreatefromjpeg($caminhoOriginal);
            if ($imgOriginal && function_exists('exif_read_data')) {
                $exif = @exif_read_data($caminhoOriginal);
                if (!empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3: $imgOriginal = imagerotate($imgOriginal, 180, 0); break;
                        case 6: $imgOriginal = imagerotate($imgOriginal, -90, 0); break;
                        case 8: $imgOriginal = imagerotate($imgOriginal, 90, 0); break;
                    }
                }
            }
        }

        if (!$imgOriginal) {
            return false;
        }

        $larguraOrig = imagesx($imgOriginal);
        $alturaOrig = imagesy($imgOriginal);
        $alturaFocal = (int)(($alturaOrig / $larguraOrig) * $larguraFocal);

        $imgRedimensionada = imagecreatetruecolor($larguraFocal, $alturaFocal);
        imagecopyresampled($imgRedimensionada, $imgOriginal, 0, 0, 0, 0, $larguraFocal, $alturaFocal, $larguraOrig, $alturaOrig);
        imagedestroy($imgOriginal);

        $brilhoTotal = 0;
        $amostras = 0;
        for ($y = 0; $y < $alturaFocal; $y += 10) {
            for ($x = 0; $x < $larguraFocal; $x += 10) {
                $rgb = imagecolorat($imgRedimensionada, $x, $y);
                $brilhoTotal += ((($rgb >> 16) & 0xFF) * 0.299 + (($rgb >> 8) & 0xFF) * 0.587 + ($rgb & 0xFF) * 0.114);
                $amostras++;
            }
        }
        $luminanciaMedia = ($brilhoTotal / $amostras) / 255;

        $fatorGamma = 1.0; 
        $fatorContraste = 1.0;

        if ($aplicarAutomacao) {
            $alvoLuminancia = 0.40;
            $desvio = $luminanciaMedia - $alvoLuminancia;
            $fatorGamma = 1.0 + ($desvio * 0.65); 
            $fatorContraste = 1.0 + (abs($desvio) * 0.20);
        }

        $matriz = [];
        for ($y = 0; $y < $alturaFocal; $y++) {
            for ($x = 0; $x < $larguraFocal; $x++) {
                $rgb = imagecolorat($imgRedimensionada, $x, $y);
                $v = ((($rgb >> 16) & 0xFF) * 0.299 + (($rgb >> 8) & 0xFF) * 0.587 + ($rgb & 0xFF) * 0.114) / 255;

                if ($aplicarAutomacao) {
                    $v = pow($v, $fatorGamma);
                    $v = (($v - 0.5) * $fatorContraste) + 0.5;
                }
                
                $matriz[$y][$x] = max(0, min(1, $v)) * 255;
            }
        }
        imagedestroy($imgRedimensionada);

        for ($y = 0; $y < $alturaFocal; $y++) {
            for ($x = 0; $x < $larguraFocal; $x++) {
                $velhoPixel = $matriz[$y][$x];
                $novoPixel = ($velhoPixel > 128) ? 255 : 0;
                $matriz[$y][$x] = $novoPixel;

                $erro = ($velhoPixel - $novoPixel) / 8;

                if ($x + 1 < $larguraFocal)  $matriz[$y][$x+1]     += $erro;
                if ($x + 2 < $larguraFocal)  $matriz[$y][$x+2]     += $erro;
                if ($y + 1 < $alturaFocal) {
                    if ($x - 1 >= 0)         $matriz[$y+1][$x-1]   += $erro;
                                             $matriz[$y+1][$x]     += $erro;
                    if ($x + 1 < $larguraFocal)  $matriz[$y+1][$x+1]   += $erro;
                }
                if ($y + 2 < $alturaFocal)   $matriz[$y+2][$x]     += $erro;
            }
        }

        $imgFinal = imagecreate($larguraFocal, $alturaFocal);
        $alocadaBG = imagecolorallocate($imgFinal, $corBG[0], $corBG[1], $corBG[2]);
        $alocadaFG = imagecolorallocate($imgFinal, $corFG[0], $corFG[1], $corFG[2]);

        for ($y = 0; $y < $alturaFocal; $y++) {
            for ($x = 0; $x < $larguraFocal; $x++) {
                $cor = ($matriz[$y][$x] > 128) ? $alocadaBG : $alocadaFG;
                imagesetpixel($imgFinal, $x, $y, $cor);
            }
        }

        $result = imagegif($imgFinal, $caminhoDestino);
        imagedestroy($imgFinal);

        return $result;
    }
}

