<?php
function scan($dir)
{
    global $base, $site, $pages, $counter;

    $entries = scandir($dir);
    foreach ($entries as $entry) {
        if (
            $entry !== "." &&
            $entry !== ".." &&
            !str_starts_with($entry, "_")
        ) {
            $path = $dir . DS . $entry;
            if (is_file($path)) {
                $page = parse($path);
                if ($page) {
                    // echo "Pushing ".$page['slug']."\n";
                    // echo "Total pages pushed: ".sizeof($pages)."\n";
                    array_push($pages, $page);
                }
            } elseif (is_dir($path)) {
                if (
                    !str_contains($dir, "_engine") and
                    !str_contains($dir, "_site")
                ) {
                    scan($path);
                }
            }
        }
    }
}

function getDirContents($dir, &$results = [])
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

function sortByDate($pages)
{
    usort($pages, function ($a, $b) {
        if (!isset($a["date"])) {
            $a["date"] = -1;
        }

        if (!isset($b["date"])) {
            $b["date"] = -1;
        }

        return $b["date"] - $a["date"];
    });

    return $pages;
}

function recursive_ksort(&$array)
{
    foreach ($array as &$value) {
        if (is_array($value))
            recursive_ksort($value);
    }
    ksort($array, SORT_STRING | SORT_FLAG_CASE);
}
function utf8ToAscii($str, $unknown = '?')
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
function slugize($str)
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
function getoriginalcontent($slug, $lang)
{
    global $urltranslations;
    foreach ($urltranslations as $key => $val) {
        if (stripos($val[$lang], $slug) !== false) {
            return $key;
        }
    }
    return "";
}
function beautifyhtml($html)
{
    if (empty($html)) {
        return "";
    }
    $beautify = new Beautify_Html(array(
        'indent_inner_html' => false,
        'indent_char' => " ",
        'indent_size' => 2,
        'wrap_line_length' => 32786,
        'unformatted' => ['code', 'pre'],
        'preserve_newlines' => false,
        'max_preserve_newlines' => 32786,
        'indent_scripts'    => 'normal', // keep|separate|normal
    ));
    return ($beautify->beautify($html));
}
function minifyhtml($html)
{
    if (empty($html)) {
        return "";
    }
    $minifier = new TinyHtmlMinifier([
        'collapse_whitespace' => true,
        'disable_comments' => true,
    ]);
    return $minifier->minify($html);
}
