<?php

declare(strict_types=1);

// Remove accents from a string
// From https://github.com/Behat/Transliterator/blob/master/src/Transliterator.php

function unaccent(string $string): string
{
    if (!preg_match('/[\x80-\xff]/', $string)) {
        return $string;
    }

    static $chars = null;
    if ($chars === null) {
        $chars = require dirname(__DIR__, 2) . '/_data/chars.php'; //NOSONAR
    }

    return strtr($string, $chars);
}
