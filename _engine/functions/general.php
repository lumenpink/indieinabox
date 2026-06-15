<?php

function scan(string $dir)
{
    global $base, $site, $pages, $counter;

    $entries = scandir($dir);
    foreach ($entries as $entry) {
        if (
            $entry !== "."
            && $entry !== ".."
            && substr($entry, 0, 1) !== "_"
            && substr($entry, 0, 1) !== "."
        ) {
            $path = $dir . DS . $entry;
            if (is_file($path)) {
                $page = parse($path);
                if ($page) {
                    // echo "Pushing ".$page['slug']."\n";
                    // echo "Total pages pushed: ".sizeof($pages)."\n";
                    if ($pages instanceof \Indieinabox\Pages) {
                        $pages->add($page);
                    } else {
                        $pages[] = $page;
                    }
                }
            } elseif (is_dir($path)) {
                if (
                    !strpos($dir, "_engine")
                    && !strpos($dir, "_site")
                ) {
                    scan($path);
                }
            }
        }
    }
}

function getDirContents(string $dir, array &$results = [])
{
    $files = scandir($dir);

    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            $results[] = $path;
        } elseif ($value != "." && $value != "..") {
            getDirContents($path, $results);
            $results[] = $path;
        }
    }

    return $results;
}

function sortByDate(array $pages)
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

function recursive_ksort(array &$array)
{
    foreach ($array as &$value) {
        if (is_array($value)) {
            recursive_ksort($value);
        }
    }
    ksort($array, SORT_STRING | SORT_FLAG_CASE);
}
function utf8ToAscii(string $str, string $unknown = '?')
{
    static $UTF8_TO_ASCII;

    if (strlen($str) == 0) {
        return '';
    }

    preg_match_all('/.{1}|[^\x00]{1,1}$/us', $str, $ar);
    $chars = $ar[0];

    foreach ($chars as $i => $c) {
        if (ord($c[0]) >= 0 && ord($c[0]) <= 127) {
            continue;
        } // ASCII - next please
        $ord = 0;
        if (ord($c[0]) >= 192 && ord($c[0]) <= 223) {
            $ord = (ord($c[0]) - 192) * 64 + (ord($c[1]) - 128);
        }
        if (ord($c[0]) >= 224 && ord($c[0]) <= 239) {
            $ord = (ord($c[0]) - 224) * 4096 + (ord($c[1]) - 128) * 64 + (ord($c[2]) - 128);
        }
        if (ord($c[0]) >= 240 && ord($c[0]) <= 247) {
            $ord = (ord($c[0]) - 240) * 262144 + (ord($c[1]) - 128) * 4096 + (ord($c[2]) - 128) * 64 + (ord($c[3]) - 128);
        }
        if (ord($c[0]) >= 248 && ord($c[0]) <= 251) {
            $ord = (ord($c[0]) - 248) * 16777216 + (ord($c[1]) - 128) * 262144 + (ord($c[2]) - 128) * 4096 + (ord($c[3]) - 128) * 64 + (ord($c[4]) - 128);
        }
        if (ord($c[0]) >= 252 && ord($c[0]) <= 253) {
            $ord = (ord($c[0]) - 252) * 1073741824 + (ord($c[1]) - 128) * 16777216 + (ord($c[2]) - 128) * 262144 + (ord($c[3]) - 128) * 4096 + (ord($c[4]) - 128) * 64 + (ord($c[5]) - 128);
        }
        if (ord($c[0]) >= 254 && ord($c[0]) <= 255) {
            $chars[$i] = $unknown;
            continue;
        } //error

        $bank = $ord >> 8;

        if (!array_key_exists($bank, (array) $UTF8_TO_ASCII)) {
            $bankfile = __DIR__ . '/data/' . sprintf('x%02x', $bank) . '.php';
            if (file_exists($bankfile)) {
                include $bankfile;
            } else {
                $UTF8_TO_ASCII[$bank] = array();
            }
        }

        $newchar = $ord & 255;
        if (array_key_exists($newchar, $UTF8_TO_ASCII[$bank])) {
            $chars[$i] = $UTF8_TO_ASCII[$bank][$newchar];
        } else {
            $chars[$i] = $unknown;
        }
    }

    return implode('', $chars);
}
function slugize(string $str)
{
    $str = urldecode($str);
    $str = str_replace(' ', '-', trim($str));
    $str = unaccent($str);
    $str = strtolower($str);
    //Remove everything that is not a letter, number or dash
    $str = preg_replace('/[^a-z0-9-]/', '', $str);
    $str = trim($str);
    return $str;
}
function getoriginalcontent(string $slug, string $lang)
{
    global $urltranslations;
    foreach ($urltranslations as $key => $val) {
        if (stripos($val[$lang], $slug) !== false) {
            return $key;
        }
    }
    return "";
}
function beautifyhtml(string $html)
{
    if (empty($html)) {
        return "";
    }
    $beautify = new Beautify_Html(
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
    return ($beautify->beautify($html));
}
function minifyhtml(string $html)
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
if (!function_exists('str_starts_with')) {
    /**
     * Checks if a string starts with a given prefix
     *
     * @param  string $haystack The string to search in
     * @param  string $needle   The prefix to search for
     * @return bool Returns true if the string starts with the prefix, false otherwise
     */
    function str_starts_with($haystack, $needle)
    {
        return substr_compare($haystack, $needle, 0, strlen($needle)) === 0;
    }
}

/**
 * Recursively removes a directory and all its contents
 *
 * @param  string $dir         The directory path to remove
 * @param  bool   $keepRootDir If true, keeps the root directory but removes all contents
 * @return bool Returns true on success, false on failure
 * @throws RuntimeException If directory cannot be removed due to permissions or other issues
 */
function recursive_rmdir($dir, $keepRootDir = false)
{
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        throw new RuntimeException("'$dir' is not a directory");
    }

    // Ensure the path has a trailing slash
    $dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;

    try {
        $items = new DirectoryIterator($dir);

        foreach ($items as $item) {
            // Skip dot files
            if ($item->isDot()) {
                continue;
            }

            $path = $item->getPathname();

            if ($item->isDir()) {
                // Recursively remove subdirectory
                if (!recursive_rmdir($path)) {
                    return false;
                }
            } else {
                // Remove file
                if (!unlink($path)) {
                    throw new RuntimeException("Failed to delete file: $path");
                }
            }
        }

        // Remove the root directory itself if not keeping it
        if (!$keepRootDir && !rmdir($dir)) {
            throw new RuntimeException("Failed to remove directory: $dir");
        }

        return true;
    } catch (Exception $e) {
        throw new RuntimeException("Error while removing directory: " . $e->getMessage());
    }
}
