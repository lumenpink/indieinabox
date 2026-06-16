<?php

function scan(string $dir): void
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
                    strpos($path, DIRECTORY_SEPARATOR . "app") === false
                    && strpos($path, DIRECTORY_SEPARATOR . "bootstrap") === false
                    && strpos($path, DIRECTORY_SEPARATOR . "vendor") === false
                    && strpos($path, DIRECTORY_SEPARATOR . "resources") === false
                    && strpos($path, DIRECTORY_SEPARATOR . "data") === false
                    && strpos($path, DIRECTORY_SEPARATOR . $site->outputdir) === false
                ) {
                    scan($path);
                }
            }
        }
    }
}

/**
 * @param string $dir
 * @param array<int, string> $results
 * @return array<int, string>
 */
function getDirContents(string $dir, array &$results = []): array
{
    return \Indieinabox\Helper::getDirContents($dir, $results);
}

/**
 * @param array<int, array<string, mixed>|\Indieinabox\Page> $pages
 * @return array<int, array<string, mixed>|\Indieinabox\Page>
 */
function sortByDate(array $pages): array
{
    return \Indieinabox\Helper::sortByDate($pages);
}

/**
 * @param array<string, mixed> $array
 * @return void
 */
function recursive_ksort(array &$array): void
{
    \Indieinabox\Helper::recursive_ksort($array);
}

function utf8ToAscii(string $str, string $unknown = '?'): string
{
    return \Indieinabox\Helper::utf8ToAscii($str, $unknown);
}

function slugize(string $str): string
{
    return \Indieinabox\Helper::slugize($str);
}

function getoriginalcontent(string $slug, string $lang): string
{
    return \Indieinabox\Helper::getoriginalcontent($slug, $lang);
}

function beautifyhtml(string $html): string
{
    return \Indieinabox\Helper::beautifyhtml($html);
}

function minifyhtml(string $html): string
{
    return \Indieinabox\Helper::minifyhtml($html);
}

if (!function_exists('str_starts_with')) {
    /**
     * Checks if a string starts with a given prefix
     *
     * @param  string $haystack The string to search in
     * @param  string $needle   The prefix to search for
     * @return bool Returns true if the string starts with the prefix, false otherwise
     */
    function str_starts_with(string $haystack, string $needle): bool
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
function recursive_rmdir(string $dir, bool $keepRootDir = false): bool
{
    return \Indieinabox\Helper::recursive_rmdir($dir, $keepRootDir);
}
