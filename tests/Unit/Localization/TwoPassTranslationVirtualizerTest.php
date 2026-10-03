<?php

declare(strict_types=1);

use Indieinabox\Localization\Contracts\AiTranslationProviderInterface;
use Indieinabox\Localization\Engines\AiTranslationEngine;
use Indieinabox\Localization\Services\TranslationCacheService;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Localization;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\TranslationVirtualizer;
use Indieinabox\Theme\ThemeHelper;

it('executes two-pass AI translation architecture end-to-end', function () {
    $tempDir = sys_get_temp_dir() . '/two_pass_test_' . uniqid('', true);
    mkdir($tempDir, 0777, true);

    $site = new Site();
    $site->localization = new Localization();
    $site->localization->lang = ['en', 'pt'];
    $site->localization->defaultLang = 'en';
    $site->options->translation_parity = 'full';
    $site->options->translation_auto = 'gemini';
    $site->options->prettylinks = true;

    // Create a mock provider that mimics translation with conversational filler
    $mockProvider = new class implements AiTranslationProviderInterface {
        public function getName(): string { return 'gemini'; }
        public function getLabel(): string { return 'Google Gemini'; }
        public function isAvailable(): bool { return true; }
        public function getModelName(): string { return 'gemini-2.5-flash'; }
        public function translate(string $text, string $targetLang, string $sourceLang = 'en'): string {
            if ($text === 'Welcome Guide') {
                return 'Guia de Boas-Vindas';
            }
            if ($text === 'Gardening') {
                return 'Jardinagem';
            }
            if ($text === 'plants') {
                return 'plantas';
            }
            // Markdown content with preamble and closing
            return "Here is the translated article:\n"
                . "# Guia de Boas-Vindas\n\n"
                . "Leia mais no [nosso guia](__LNK_TARGET_0__) para aprender.\n\n"
                . "Hope this helps!";
        }
    };

    $cache = new TranslationCacheService($tempDir);
    $engine = new AiTranslationEngine($mockProvider, $cache);

    $virtualizer = new TranslationVirtualizer($site, null, null, $engine);

    $pages = new Pages();
    $page = Page::fromArray([
        'lang' => 'en',
        'kind' => 'page',
        'nick' => 'welcome-guide',
        'slug' => 'welcome-guide/',
        'title' => 'Welcome Guide',
        'content' => "# Welcome Guide\n\nRead more in [our guide](/welcome-guide/) to learn.",
        'rawBody' => "# Welcome Guide\n\nRead more in [our guide](/welcome-guide/) to learn.",
        'flowerbed' => ['Gardening'],
        'tags' => ['plants', 'draft'],
    ]);
    $pages->add($page);

    $virtualizer->virtualize($pages);

    expect($pages)->toHaveCount(2);

    $ptPage = null;
    foreach ($pages as $p) {
        if ($p->lang === 'pt') {
            $ptPage = $p;
            break;
        }
    }

    expect($ptPage)->not->toBeNull();

    // 1. Pass 1 verification: Title translated, slug derived, taxonomies translated
    expect($ptPage->title)->toBe('Guia de Boas-Vindas');
    expect($ptPage->slug)->toBe('pt/guia-de-boas-vindas/');
    expect($ptPage->metadata->flowerbed)->toBe(['Jardinagem']);
    expect($ptPage->metadata->tags)->toBe(['plantas', 'draft']);

    // 2. Pass 2 verification: AI frontmatter metadata present
    expect($ptPage->metadata->translated_by)->toBe('ai');
    expect($ptPage->metadata->translated_by_ia)->toBeTrue();
    expect($ptPage->metadata->translation_ai_provider)->toBe('Google Gemini');
    expect($ptPage->metadata->translation_ai_model)->toBe('gemini-2.5-flash');
    expect($ptPage->metadata->translation_source_lang)->toBe('en');

    // 3. Sanitizer verification: Preambles and closings stripped
    expect($ptPage->content->content)->not->toContain('Here is the translated article:');
    expect($ptPage->content->content)->not->toContain('Hope this helps!');

    // 4. Positional Link Masker verification: Internal link rewritten to translated slug
    expect($ptPage->content->content)->toContain('/pt/guia-de-boas-vindas/');

    // 5. ThemeHelper notice verification
    $notice = ThemeHelper::getAITranslationNotice($ptPage);
    expect($notice)->toContain('Google Gemini');
    expect($notice)->toContain('gemini-2.5-flash');
    expect($notice)->toContain('en');

    // Cleanup
    array_map('unlink', glob("$tempDir/*.*"));
    @rmdir($tempDir);
});
