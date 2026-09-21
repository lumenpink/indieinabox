<?php

declare(strict_types=1);

use Indieinabox\Localization\IsoLanguages;

test('IsoLanguages catalog returns comprehensive language list', function () {
    $catalog = IsoLanguages::getAll();
    expect($catalog)->toBeArray()
        ->and(count($catalog))->toBeGreaterThan(20)
        ->and($catalog)->toHaveKeys(['en', 'pt', 'pt-BR', 'es', 'fr', 'de', 'ja']);
});

test('IsoLanguages resolves exact codes and regional variants', function () {
    $ptBr = IsoLanguages::get('pt-BR');
    expect($ptBr)->not->toBeNull()
        ->and($ptBr['native_name'])->toBe('Português (Brasil)')
        ->and($ptBr['short_code'])->toBe('PT');

    $enUs = IsoLanguages::get('en_US'); // normalizes underscore
    expect($enUs)->not->toBeNull()
        ->and($enUs['native_name'])->toBe('English (US)')
        ->and($enUs['short_code'])->toBe('EN');

    $ja = IsoLanguages::get('ja');
    expect($ja)->not->toBeNull()
        ->and($ja['native_name'])->toBe('日本語')
        ->and($ja['short_code'])->toBe('JA');
});

test('IsoLanguages falls back cleanly on unknown regional codes', function () {
    $ptAo = IsoLanguages::get('pt-AO'); // Angolan Portuguese falls back to base Portuguese
    expect($ptAo)->not->toBeNull()
        ->and($ptAo['native_name'])->toBe('Português')
        ->and($ptAo['short_code'])->toBe('PT');

    expect(IsoLanguages::getShortCode('xyz-ABC'))->toBe('XYZ')
        ->and(IsoLanguages::getNativeName('xyz-ABC'))->toBe('xyz-ABC');
});

test('IsoLanguages getLabel respects native and short display modes', function () {
    expect(IsoLanguages::getLabel('pt-BR', 'native'))->toBe('Português (Brasil)')
        ->and(IsoLanguages::getLabel('pt-BR', 'short'))->toBe('PT')
        ->and(IsoLanguages::getLabel('en', 'native'))->toBe('English')
        ->and(IsoLanguages::getLabel('en', 'short'))->toBe('EN')
        ->and(IsoLanguages::getLabel('es', 'short'))->toBe('ES');
});
