<?php

declare(strict_types=1);

namespace Parsedown;

trait ParsedownUtilities
{
    protected static function escape(string $text, bool $allowQuotes = false): string
    {
        return htmlspecialchars($text, $allowQuotes ? ENT_NOQUOTES : ENT_QUOTES, 'UTF-8');
    }

    protected static function striAtStart(string $string, string $needle): bool
    {
        $len = strlen($needle);
        if ($len > strlen($string)) {
            return false;
        }
        return strtolower(substr($string, 0, $len)) === strtolower($needle);
    }
}
