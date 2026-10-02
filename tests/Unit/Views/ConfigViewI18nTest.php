<?php

declare(strict_types=1);

use Indieinabox\Views\Admin\ConfigView;

it('renders categorized i18n pills, real-time search, and AI model recommendations in ConfigView', function () {
    $config = [
        'ai_translation_provider' => 'gemini',
        'ai_translation_model' => 'gemini-2.5-flash',
        'translations' => [
            'Home' => ['pt' => 'Início'],
            'Like' => ['pt' => 'Curtir'],
        ],
    ];

    $site = new \Indieinabox\Site\Site();
    $site->metadata->fqdn = 'https://lumen.pink';
    $site->localization->lang = ['pt', 'en', 'es'];

    $html = ConfigView::renderConfig($site, $config);

    // AI model recommendation selection
    expect($html)->toContain('id="ai_model_recommended_select"');
    expect($html)->toContain('id="ai_translation_model"');

    // i18n search and categories
    expect($html)->toContain('id="i18n_filter_search"');
    expect($html)->toContain('btn-i18n-cat');
    expect($html)->toContain('Navegação &amp; Cabeçalhos');
    expect($html)->toContain('Interações &amp; Federação');
    expect($html)->toContain('data-category="nav"');
    expect($html)->toContain('data-category="interactions"');
});
