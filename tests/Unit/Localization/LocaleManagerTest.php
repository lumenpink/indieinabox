<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use Indieinabox\Localization\LocaleManager;

test('LocaleManager resolves bundled and on-disk locales for pt, es, en', function () {
    $pt = LocaleManager::getLocale('pt');
    expect($pt)->not->toBeNull()
        ->and($pt['code'])->toBe('pt')
        ->and($pt['translations']['Home'])->toBe('Início')
        ->and($pt['translations']['Recent posts'])->toBe('Publicações recentes')
        ->and($pt['kinds']['article']['title'])->toBe('Artigos')
        ->and($pt['kinds']['note']['title'])->toBe('Notas');

    $es = LocaleManager::getLocale('es');
    expect($es)->not->toBeNull()
        ->and($es['code'])->toBe('es')
        ->and($es['translations']['Home'])->toBe('Inicio')
        ->and($es['translations']['Recent posts'])->toBe('Publicaciones recientes')
        ->and($es['kinds']['article']['title'])->toBe('Artículos');

    $en = LocaleManager::getLocale('en');
    expect($en)->not->toBeNull()
        ->and($en['code'])->toBe('en')
        ->and($en['translations']['Home'])->toBe('Home');
});

test('LocaleManager normalizes regional subtags like pt-BR and es-ES', function () {
    $ptBr = LocaleManager::getLocale('pt-BR');
    expect($ptBr)->not->toBeNull()
        ->and($ptBr['translations']['About'])->toBe('Sobre');

    $esEs = LocaleManager::getLocale('es-ES');
    expect($esEs)->not->toBeNull()
        ->and($esEs['translations']['About'])->toBe('Acerca de');
});

test('LocaleManager returns null for unknown locales without failing', function () {
    $unknown = LocaleManager::getLocale('xx-unknown', false);
    expect($unknown)->toBeNull();
});

test('LocaleManager applies locale translations and kind structures to config array', function () {
    $config = [
        'lang' => ['en', 'pt'],
        'translations' => [
            'Home' => ['en' => 'Home'],
            'About' => ['en' => 'About', 'pt' => 'Custom Sobre'],
        ],
        'kinds' => [
            'article' => [
                'title' => ['en' => 'Articles'],
                'content_dir' => ['en' => 'articles'],
            ],
        ],
    ];

    $applied = LocaleManager::applyLocale($config, 'pt', false);
    expect($applied)->toBeTrue()
        // Empty/missing phrase filled
        ->and($config['translations']['Home']['pt'])->toBe('Início')
        // Pre-existing translation preserved when overwrite is false
        ->and($config['translations']['About']['pt'])->toBe('Custom Sobre')
        // Kind title and content_dir filled
        ->and($config['kinds']['article']['title']['pt'])->toBe('Artigos')
        ->and($config['kinds']['article']['content_dir']['pt'])->toBe('artigos');
});

test('LocaleManager lists supported locales', function () {
    $locales = LocaleManager::getSupportedLocales();
    expect($locales)->toContain('en')
        ->toContain('pt')
        ->toContain('es');
});

test('LocaleManager loads cached locale from data/locales directory', function () {
    $cacheDir = LocaleManager::getCacheDir();
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }
    $fakeLocale = [
        'code' => 'zz',
        'name' => 'Fake Language',
        'translations' => ['Home' => 'Casa Falsa'],
        'kinds' => ['article' => ['title' => 'Falsos', 'content_dir' => 'falsos']],
    ];
    $filePath = $cacheDir . '/zz.json';
    file_put_contents($filePath, (string) json_encode($fakeLocale));

    try {
        $loaded = LocaleManager::getLocale('zz', false);
        expect($loaded)->not->toBeNull()
            ->and($loaded['code'])->toBe('zz')
            ->and($loaded['translations']['Home'])->toBe('Casa Falsa');
    } finally {
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }
});

test('LocaleManager auto-fills default English kind fallbacks but preserves custom titles', function () {
    $config = [
        'lang' => ['en', 'pt-BR'],
        'kinds' => [
            'garden' => [
                'title' => [
                    'en' => 'Digital Garden',
                    'pt-BR' => 'Digital Garden', // English fallback copied on lang add
                ],
                'content_dir' => [
                    'en' => 'garden',
                    'pt-BR' => 'garden',
                ],
            ],
            'reply' => [
                'title' => [
                    'en' => 'Replies',
                    'pt-BR' => 'reply', // Kind key default
                ],
                'content_dir' => [
                    'en' => 'replies',
                    'pt-BR' => 'replies',
                ],
            ],
            'article' => [
                'title' => [
                    'en' => 'Articles',
                    'pt-BR' => 'Meus Artigos Customizados', // Custom title
                ],
                'content_dir' => [
                    'en' => 'articles',
                    'pt-BR' => 'artigos-custom',
                ],
            ],
        ],
    ];

    $applied = LocaleManager::applyLocale($config, 'pt-BR', false);
    expect($applied)->toBeTrue();

    // Default English fallbacks should be replaced with localized Portuguese
    expect($config['kinds']['garden']['title']['pt-BR'])->toBe('Jardim Digital')
        ->and($config['kinds']['garden']['content_dir']['pt-BR'])->toBe('jardins')
        ->and($config['kinds']['reply']['title']['pt-BR'])->toBe('Respostas')
        ->and($config['kinds']['reply']['content_dir']['pt-BR'])->toBe('respostas');

    // Custom titles and paths should be preserved when overwrite is false
    expect($config['kinds']['article']['title']['pt-BR'])->toBe('Meus Artigos Customizados')
        ->and($config['kinds']['article']['content_dir']['pt-BR'])->toBe('artigos-custom');

    // When overwrite is true, even custom titles are reset to locale standard
    LocaleManager::applyLocale($config, 'pt-BR', true);
    expect($config['kinds']['article']['title']['pt-BR'])->toBe('Artigos')
        ->and($config['kinds']['article']['content_dir']['pt-BR'])->toBe('artigos');
});
