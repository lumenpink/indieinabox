<?php

declare(strict_types=1);

// Just translate the text
function translate(string $text, ?string $lang = null): string
{
    global $translations, $page, $p, $site;
    if ($lang == null) {
        if (isset($p)) {
            $lang = $p instanceof \Indieinabox\Page ? $p->lang : ($p["lang"] ?? "en");
        } elseif (isset($page)) {
            $lang = $page instanceof \Indieinabox\Page ? $page->lang : ($page["lang"] ?? "en");
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
    // Empty translation for $text in $lang or no translations for $lang;
    if (!isset($found) || empty($found)) {

        $translations[$lang][$text] = '';
        updateTranslations();
        return $text;
    }
    return $translations[$lang][$found];
}
// Translate the text and make it lowercase
function translateLowercase(string $text): string
{
    return strtolower(translate($text));
}
// Translate the text and slugize
function translateSlugize(string $text): string
{
    return slugize(translate($text));
}
// Update the translations file
function updateTranslations(): void
{
    global $translations, $site;
    $file = $site->paths->baseDir . DIRECTORY_SEPARATOR . "_data/translations.php";
    recursive_ksort($translations);
    file_put_contents(
        $file,
        "<?php\nglobal \$translations;\n\$translations= "
            . var_export($translations, true)
            . ";\n?>"
    );
}

// Shorthand aliases for templates
function t(string $text, ?string $lang = null): string
{
    return translate($text, $lang);
}

function ts(string $text): string
{
    return translateSlugize($text);
}

function tl(string $text): string
{
    return translateLowercase($text);
}
