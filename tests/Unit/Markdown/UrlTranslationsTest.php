<?php

declare(strict_types=1);

use Indieinabox\Translations\UrlTranslations;

it('UrlTranslations returns translated slug correctly', function () {
    $translations = [
        'about' => [
            'es' => 'sobre',
            'fr' => 'a-propos',
        ],
    ];

    $urlTranslations = new UrlTranslations($translations);

    expect($urlTranslations->getTranslatedSlug('about', 'es'))->toBe('sobre')
        ->and($urlTranslations->getTranslatedSlug('about', 'fr'))->toBe('a-propos')
        ->and($urlTranslations->getTranslatedSlug('about', 'de'))->toBe('about'); // Fallback to original
});

it('UrlTranslations returns original content correctly', function () {
    $translations = [
        'about' => [
            'en' => 'about',
            'es' => 'sobre',
        ],
    ];

    $urlTranslations = new UrlTranslations($translations);

    expect($urlTranslations->getOriginalContent('about', 'en'))->toBe('about')
        ->and($urlTranslations->getOriginalContent('about', 'es'))->toBe('sobre')
        ->and($urlTranslations->getOriginalContent('unknown', 'en'))->toBe('unknown'); // Fallback to original
});
