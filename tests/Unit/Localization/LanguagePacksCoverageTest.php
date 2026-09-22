<?php

declare(strict_types=1);

use Indieinabox\Localization\LocaleManager;

it('verifies all bundled locale files exist and parse as valid JSON', function () {
    $locales = ['en', 'pt', 'pt-BR', 'es', 'fr', 'de'];

    foreach ($locales as $code) {
        $locale = LocaleManager::getLocale($code, false);
        expect($locale)->not->toBeNull("Locale '{$code}' should be loadable locally");
        expect($locale)->toHaveKey('code');
        expect($locale)->toHaveKey('name');
        expect($locale)->toHaveKey('translations');
        expect($locale['translations'])->toBeArray();
        expect($locale)->toHaveKey('kinds');
    }
});

it('verifies all language packs have 100% string coverage matching English baseline', function () {
    $enLocale = LocaleManager::getLocale('en', false);
    expect($enLocale)->not->toBeNull();
    $expectedKeys = array_keys($enLocale['translations']);

    $locales = ['pt', 'pt-BR', 'es', 'fr', 'de'];

    foreach ($locales as $code) {
        $locale = LocaleManager::getLocale($code, false);
        expect($locale)->not->toBeNull();
        $translations = $locale['translations'];

        foreach ($expectedKeys as $key) {
            expect(array_key_exists($key, $translations))->toBeTrue("Locale '{$code}' is missing key '{$key}'");
            expect(trim($translations[$key]))->not->toBeEmpty("Locale '{$code}' has empty translation for '{$key}'");
        }
    }
});

it('applies locale presets to configuration array cleanly without overriding unless specified', function () {
    $config = [
        'lang' => ['pt-BR', 'en'],
        'translations' => [
            'Home' => ['pt-BR' => 'Meu Início']
        ]
    ];

    $applied = LocaleManager::applyLocale($config, 'pt-BR', false);
    expect($applied)->toBeTrue();
    // Existing value preserved
    expect($config['translations']['Home']['pt-BR'])->toBe('Meu Início');
    // Missing keys populated
    expect($config['translations']['About']['pt-BR'])->toBe('Sobre');
    expect($config['translations']['Flowerbed']['pt-BR'])->toBe('Canteiro');

    // Force overwrite
    LocaleManager::applyLocale($config, 'pt-BR', true);
    expect($config['translations']['Home']['pt-BR'])->toBe('Início');
});
