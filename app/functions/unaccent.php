<?php

declare(strict_types=1);

// Remove accents from a string
// From https://github.com/Behat/Transliterator/blob/master/src/Transliterator.php

function unaccent(string $string): string
{
    return \Indieinabox\Helper::unaccent($string);
}
