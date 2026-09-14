<?php

declare(strict_types=1);

use Indieinabox\Localization\Translator;
use Indieinabox\Support\DateFormatter;
use Indieinabox\Support\FileUtils;
use Indieinabox\Support\HtmlUtils;
use Indieinabox\Support\TextParser;
use Indieinabox\Taxonomy\KindHelper;

function t(string $text, ?string $lang = null): string
{
    return Translator::translate($text, $lang);
}

function ts(string $text): string
{
    return Translator::translateSlugize($text);
}

function tl(string $text): string
{
    return Translator::translateLowercase($text);
}

function translate(string $text, ?string $lang = null): string
{
    return Translator::translate($text, $lang);
}

function translateLowercase(string $text): string
{
    return Translator::translateLowercase($text);
}

function translateSlugize(string $text): string
{
    return Translator::translateSlugize($text);
}

function updateTranslations(): void
{
    Translator::updateTranslations();
}

/**
 * @param \Indieinabox\Page|array<string, mixed> $page
 * @return array{long: string, iso: string}
 */
function localizeddate($page): array
{
    return DateFormatter::localizeddate($page);
}

function listposts(): string
{
    return KindHelper::listposts();
}

/**
 * @param mixed $var
 * @return bool
 */
function removegeneric($var): bool
{
    return KindHelper::removeGeneric($var);
}

/**
 * @param \Indieinabox\Page|array<string, mixed> $page
 * @return array{localized: string, kind: string}
 */
function kind($page): array
{
    return KindHelper::kind($page);
}

function slugize(string $str): string
{
    return TextParser::slugize($str);
}

function unaccent(string $string): string
{
    return TextParser::unaccent($string);
}

function beautifyhtml(string $html): string
{
    return HtmlUtils::beautify($html);
}

function minifyhtml(string $html): string
{
    return HtmlUtils::minify($html);
}

/**
 * @param string $dir
 * @param bool $keepRootDir
 * @return bool
 */
function recursiveRmdir(string $dir, bool $keepRootDir = false): bool
{
    return FileUtils::recursiveRmdir($dir, $keepRootDir);
}

/**
 * @param string $dir
 * @param array<int, string> $results
 * @return array<int, string>
 */
function getDirContents(string $dir, array &$results = []): array
{
    return FileUtils::getDirContents($dir, $results);
}

/**
 * @param array<int, array<string, mixed>|\Indieinabox\Page> $pages
 * @return array<int, array<string, mixed>|\Indieinabox\Page>
 */
function sortByDate(array $pages): array
{
    return DateFormatter::sortByDate($pages);
}

/**
 * @param array<string, mixed> $array
 * @return void
 */
function recursiveKsort(array &$array): void
{
    FileUtils::recursiveKsort($array);
}

function getoriginalcontent(string $slug, string $lang): string
{
    return KindHelper::getOriginalContent($slug, $lang);
}
