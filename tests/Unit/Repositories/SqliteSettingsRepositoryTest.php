<?php

declare(strict_types=1);

use Indieinabox\Core\Database;
use Indieinabox\Repositories\SqliteSettingsRepository;

$tempDir = __DIR__ . '/tmp_settings_repo_unit';

beforeEach(function () use ($tempDir) {
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    Database::disconnect();
    $dbPath = $tempDir . '/test.sqlite';
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }
    Database::$dataDir = $tempDir . '/data';
    Database::connect($dbPath);
    $db = Database::getDb();
    $schema = file_get_contents(__DIR__ . '/../../../database.sql');
    $db->exec($schema);
});

afterEach(function () use ($tempDir) {
    Database::disconnect();
    Database::$dataDir = '';

    if (is_dir($tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
            @$todo($fileinfo->getPathname());
        }
        @rmdir($tempDir);
    }
});

describe('SqliteSettingsRepository', function () {
    it('sets, gets, and lists all settings', function () {
        $repo = new SqliteSettingsRepository();

        expect($repo->get('non_existent', 'default_val'))->toBe('default_val');

        $setOk = $repo->set('sitename', 'Indie Publishing Hub');
        expect($setOk)->toBeTrue();
        expect($repo->get('sitename'))->toBe('Indie Publishing Hub');

        // Handles array values serialized as JSON
        $repo->set('supported_formats', ['html', 'gemini', 'gopher']);
        expect($repo->get('supported_formats'))->toBe(['html', 'gemini', 'gopher']);

        $all = $repo->all();
        expect($all)->toBeArray();
        expect($all)->toHaveKey('sitename');
        expect($all)->toHaveKey('supported_formats');
    });

    it('manages interface translations and url translations', function () {
        $repo = new SqliteSettingsRepository();

        $translations = [
            'Home' => ['en' => 'Home', 'pt' => 'Início'],
            'Articles' => ['en' => 'Articles', 'pt' => 'Artigos'],
        ];

        $saved = $repo->saveTranslations($translations);
        expect($saved)->toBeTrue();

        $loaded = $repo->getTranslations();
        expect($loaded)->toHaveKey('Home');
        expect($loaded['Home']['pt'])->toBe('Início');

        $urlTranslations = [
            'tag' => ['en' => 'tag', 'pt' => 'etiqueta'],
        ];

        $savedUrl = $repo->saveUrlTranslations($urlTranslations);
        expect($savedUrl)->toBeTrue();

        $loadedUrls = $repo->getUrlTranslations();
        expect($loadedUrls)->toHaveKey('tag');
        expect($loadedUrls['tag']['pt'])->toBe('etiqueta');
    });

    it('manages content kinds taxonomies', function () {
        $repo = new SqliteSettingsRepository();

        $kinds = [
            'article' => ['title' => 'Articles', 'layout' => 'standard'],
            'note' => ['title' => 'Notes', 'layout' => 'micro'],
        ];

        $saved = $repo->saveKinds($kinds);
        expect($saved)->toBeTrue();

        $loaded = $repo->getKinds();
        expect($loaded)->toHaveKey('article');
        expect($loaded['article']['title'])->toBe('Articles');
    });
});
