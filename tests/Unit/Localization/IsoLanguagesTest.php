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

test('IsoLanguages getLabel respects native_short, native_long, code_short, and code_long display modes', function () {
    // native_short (default, and alias 'native')
    expect(IsoLanguages::getLabel('pt-BR', 'native_short'))->toBe('Português')
        ->and(IsoLanguages::getLabel('pt-BR', 'native'))->toBe('Português')
        ->and(IsoLanguages::getLabel('pt-BR'))->toBe('Português')
        ->and(IsoLanguages::getLabel('en-US', 'native_short'))->toBe('English')
        ->and(IsoLanguages::getLabel('zh-CN', 'native_short'))->toBe('中文');

    // native_long
    expect(IsoLanguages::getLabel('pt-BR', 'native_long'))->toBe('Português (Brasil)')
        ->and(IsoLanguages::getLabel('en-US', 'native_long'))->toBe('English (US)')
        ->and(IsoLanguages::getLabel('es', 'native_long'))->toBe('Español');

    // code_short (and alias 'short')
    expect(IsoLanguages::getLabel('pt-BR', 'code_short'))->toBe('PT')
        ->and(IsoLanguages::getLabel('pt-BR', 'short'))->toBe('PT')
        ->and(IsoLanguages::getLabel('en-US', 'code_short'))->toBe('EN');

    // code_long
    expect(IsoLanguages::getLabel('pt-BR', 'code_long'))->toBe('PT-BR')
        ->and(IsoLanguages::getLabel('en-US', 'code_long'))->toBe('EN-US')
        ->and(IsoLanguages::getLabel('es', 'code_long'))->toBe('ES');
});
