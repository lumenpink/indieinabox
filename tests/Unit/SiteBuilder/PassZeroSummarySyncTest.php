<?php

declare(strict_types=1);

use Indieinabox\Localization\Contracts\AiTranslationProviderInterface;
use Indieinabox\Localization\Engines\AiTranslationEngine;
use Indieinabox\Localization\Services\TranslationCacheService;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Services\AiSummaryService;
use Indieinabox\Site\Localization;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\TranslationVirtualizer;

it('executes Pass 0 to synchronize summaries and hashes across all language originals before virtualization', function () {
    $tempDir = sys_get_temp_dir() . '/pass0_test_' . uniqid('', true);
    mkdir($tempDir . '/en/notes', 0777, true);
    mkdir($tempDir . '/notes', 0777, true);

    $enFile = $tempDir . '/en/notes/quantum.md';
    $ptFile = $tempDir . '/notes/canteiro.md';

    file_put_contents($enFile, <<<MD
---
title: "Quantum Gardening"
date: "2026-10-03"
---
Quantum computing applied to ecosystem modeling in gardens.
MD);

    file_put_contents($ptFile, <<<MD
---
title: "Canteiro Central"
date: "2026-10-03"
---
Reflexões sobre a vida no canteiro central do jardim.
MD);

    $site = new Site();
    $site->localization = new Localization();
    $site->localization->lang = ['pt', 'en'];
    $site->localization->defaultLang = 'pt';
    $site->options->translation_parity = 'full';
    $site->options->translation_auto = 'gemini';
    $site->paths->baseDir = $tempDir;

    // Mock AI provider for translation and summary
    $mockProvider = new class implements AiTranslationProviderInterface {
        public function getName(): string { return 'gemini'; }
        public function getLabel(): string { return 'Google Gemini'; }
        public function isAvailable(): bool { return true; }
        public function getModelName(): string { return 'gemini-3.5-flash'; }
        public function translate(string $text, string $targetLang, string $sourceLang = 'en'): string {
            if ($text === 'Quantum Gardening') return 'Jardinagem Quântica';
            if ($text === 'Canteiro Central') return 'Central Flowerbed';
            if (str_contains($text, 'Quantum computing applied')) return 'Computação quântica aplicada a ecossistemas.';
            if (str_contains($text, 'Reflexões sobre')) return 'Reflections on the central flowerbed.';
            return $text;
        }
    };

    $cache = new TranslationCacheService($tempDir . '/cache');
    $engine = new AiTranslationEngine($mockProvider, $cache);

    $virtualizer = new TranslationVirtualizer($site, null, null, $engine);

    $pages = new Pages();

    $enPage = Page::fromArray([
        'lang' => 'en',
        'kind' => 'article',
        'nick' => 'quantum',
        'slug' => 'en/articles/quantum/',
        'title' => 'Quantum Gardening',
        'content' => 'Quantum computing applied to ecosystem modeling in gardens.',
        'rawBody' => 'Quantum computing applied to ecosystem modeling in gardens.',
    ]);
    $enPage->filepath = $enFile;
    $pages->add($enPage);

    $ptPage = Page::fromArray([
        'lang' => 'pt',
        'kind' => 'article',
        'nick' => 'canteiro',
        'slug' => 'articles/canteiro/',
        'title' => 'Canteiro Central',
        'content' => 'Reflexões sobre a vida no canteiro central do jardim.',
        'rawBody' => 'Reflexões sobre a vida no canteiro central do jardim.',
    ]);
    $ptPage->filepath = $ptFile;
    $pages->add($ptPage);

    // Run virtualization (Pass 0 -> Pass 1 -> Pass 2)
    $virtualizer->virtualize($pages);

    // 1. Verify Pass 0 updated the English file on disk
    $reloadedEn = (string) file_get_contents($enFile);
    expect($reloadedEn)->toContain('summary:');
    expect($reloadedEn)->toContain('summary_hash:');
    expect($reloadedEn)->toContain('summary_origin: "ai"');

    // 2. Verify Pass 0 updated the Portuguese file on disk
    $reloadedPt = (string) file_get_contents($ptFile);
    expect($reloadedPt)->toContain('summary:');
    expect($reloadedPt)->toContain('summary_hash:');
    expect($reloadedPt)->toContain('summary_origin: "ai"');

    // 3. Verify total virtualized pages (2 originals + 2 virtualized = 4)
    expect($pages)->toHaveCount(4);

    // 4. Verify PT virtualized version of the English original note
    $ptVirtualized = null;
    foreach ($pages as $p) {
        if ($p->lang === 'pt' && $p->nick !== 'canteiro') {
            $ptVirtualized = $p;
            break;
        }
    }

    expect($ptVirtualized)->not->toBeNull();
    expect($ptVirtualized->title)->toBe('Jardinagem Quântica');
    expect($ptVirtualized->metadata->translation_source)->toBe('en/articles/quantum/');
    expect($ptVirtualized->metadata->translation_source_lang)->toBe('en');
    expect($ptVirtualized->metadata->translation_source_hash)->toBe(
        AiSummaryService::computeBodyHash('Quantum computing applied to ecosystem modeling in gardens.')
    );

    // Cleanup
    unlink($enFile);
    unlink($ptFile);
    array_map('unlink', glob($tempDir . '/cache/*.*'));
    @rmdir($tempDir . '/cache');
    @rmdir($tempDir . '/en/notes');
    @rmdir($tempDir . '/en');
    @rmdir($tempDir . '/notes');
    @rmdir($tempDir);
});
