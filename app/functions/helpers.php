<?php

declare(strict_types=1);

use Indieinabox\Helper;

function t(string $text, ?string $lang = null): string
{
    return Helper::translate($text, $lang);
}

function ts(string $text): string
{
    return Helper::translateSlugize($text);
}

function tl(string $text): string
{
    return Helper::translateLowercase($text);
}

function translate(string $text, ?string $lang = null): string
{
    return Helper::translate($text, $lang);
}

function translateLowercase(string $text): string
{
    return Helper::translateLowercase($text);
}

function translateSlugize(string $text): string
{
    return Helper::translateSlugize($text);
}

function updateTranslations(): void
{
    Helper::updateTranslations();
}

/**
 * @param \Indieinabox\Page|array<string, mixed> $page
 * @return array{long: string, iso: string}
 */
function localizeddate($page): array
{
    return Helper::localizeddate($page);
}

function listposts(): string
{
    return Helper::listposts();
}

/**
 * @param mixed $var
 * @return bool
 */
function removegeneric($var): bool
{
    return Helper::removegeneric($var);
}

/**
 * @param \Indieinabox\Page|array<string, mixed> $page
 * @return array{localized: string, kind: string}
 */
function kind($page): array
{
    return Helper::kind($page);
}

function slugize(string $str): string
{
    return Helper::slugize($str);
}

function unaccent(string $string): string
{
    return Helper::unaccent($string);
}

function beautifyhtml(string $html): string
{
    return Helper::beautifyhtml($html);
}

function minifyhtml(string $html): string
{
    return Helper::minifyhtml($html);
}

/**
 * @param string $dir
 * @param bool $keepRootDir
 * @return bool
 */
function recursive_rmdir(string $dir, bool $keepRootDir = false): bool
{
    return Helper::recursive_rmdir($dir, $keepRootDir);
}

/**
 * @param string $dir
 * @param array<int, string> $results
 * @return array<int, string>
 */
function getDirContents(string $dir, array &$results = []): array
{
    return Helper::getDirContents($dir, $results);
}

/**
 * @param array<int, array<string, mixed>|\Indieinabox\Page> $pages
 * @return array<int, array<string, mixed>|\Indieinabox\Page>
 */
function sortByDate(array $pages): array
{
    return Helper::sortByDate($pages);
}

/**
 * @param array<string, mixed> $array
 * @return void
 */
function recursive_ksort(array &$array): void
{
    Helper::recursive_ksort($array);
}

function getoriginalcontent(string $slug, string $lang): string
{
    return Helper::getoriginalcontent($slug, $lang);
}
