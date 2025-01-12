<?php
// Just translate the text
function t($text, $lang = null)
{
    global $t, $p, $site;
    if ($lang == null) {
        $lang = $p["lang"];
    }
    if ($lang == $site->defaultlang) {
        return $text;
    }
    if (isset($t[$lang])) {
        foreach ($t[$lang] as $o => $v) {
            if (mb_stripos($o, $text) !== false) {
                if (!empty($v)) {
                    $found = $o;
                    break;
                }
            }
        }
    } else {
        // No translations for $lang;
        $t[$lang][$text] = '';
        updateTranslations();
        return $text;
    }
    if (!isset($found)) {
        // Empty translation for $text in $lang;
        $t[$lang][$text] = '';
        updateTranslations();
        return $text;
    }
    return $t[$lang][$found];
}
// Translate the text and make it lowercase
function tl($text)
{
    return strtolower(T($text));
}
// Translate the text ans slugize
function ts($text)
{
    return slugize(T($text));
}
// Update the translations file
function updateTranslations()
{
    global $t, $site;
    $file = $site->basedir . DS . "_data/translations.php";
    recursive_ksort($t);
    file_put_contents(
        $file,
        "<?php\nglobal \$t;\n\$t = "
            . var_export($t, true)
            . ";\n?>"
    );
}
