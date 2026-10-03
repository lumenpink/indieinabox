<?php

declare(strict_types=1);

use Indieinabox\Localization\Repositories\PersistentTaxonomyTranslationRepository;

it('persists and retrieves taxonomy translations in SQLite', function () {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $repo = new PersistentTaxonomyTranslationRepository($pdo);

    // Initial lookup returns null
    expect($repo->get('en', 'pt', 'flowerbed', 'Gardening'))->toBeNull();

    // Store flowerbed translation
    $repo->set('en', 'pt', 'flowerbed', 'Gardening', 'Jardinagem');
    expect($repo->get('en', 'pt', 'flowerbed', 'Gardening'))->toBe('Jardinagem');

    // Store tags
    $repo->set('en', 'pt', 'tag', 'indieweb', 'indieweb-pt');
    $repo->set('en', 'pt', 'tag', 'php', 'php-pt');
    expect($repo->get('en', 'pt', 'tag', 'indieweb'))->toBe('indieweb-pt');

    // Store title_slug
    $repo->set('en', 'pt', 'title_slug', 'my-english-post', 'meu-post-em-ingles');
    expect($repo->get('en', 'pt', 'title_slug', 'my-english-post'))->toBe('meu-post-em-ingles');

    // Get all for type
    $tags = $repo->getAllForType('en', 'pt', 'tag');
    expect($tags)->toBe([
        'indieweb' => 'indieweb-pt',
        'php' => 'php-pt'
    ]);
});
