<?php

declare(strict_types=1);

function translate(string $text, ?string $lang = null): string
{
    return \Indieinabox\Helper::translate($text, $lang);
}

function translateLowercase(string $text): string
{
    return \Indieinabox\Helper::translateLowercase($text);
}

function translateSlugize(string $text): string
{
    return \Indieinabox\Helper::translateSlugize($text);
}

function updateTranslations(): void
{
    \Indieinabox\Helper::updateTranslations();
}

function t(string $text, ?string $lang = null): string
{
    return \Indieinabox\Helper::translate($text, $lang);
}

function ts(string $text): string
{
    return \Indieinabox\Helper::translateSlugize($text);
}

function tl(string $text): string
{
    return \Indieinabox\Helper::translateLowercase($text);
}
