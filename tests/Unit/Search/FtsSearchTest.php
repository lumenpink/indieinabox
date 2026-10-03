<?php

declare(strict_types=1);

use Indieinabox\Console\Commands\SearchCommand;
use Indieinabox\Core\Database;
use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Search\SearchService;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\Support\FileUtils;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/indie_fts_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);
    mkdir($this->tempDir . '/content', 0777, true);
    mkdir($this->tempDir . '/data', 0777, true);

    $dbPath = $this->tempDir . '/data/test.sqlite';
    Database::connect($dbPath);
    $this->db = Database::getDb();

    $paths = new Paths($this->tempDir);
    $this->site = new Site(null, $paths);
    $this->search = new SearchService($this->db, $this->site);
});

afterEach(function () {
    Database::disconnect();
    FileUtils::recursiveRmdir($this->tempDir);
});

it('creates the search_index FTS5 virtual table', function () {
    $stmt = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='search_index'");
    $name = $stmt->fetchColumn();
    expect($name)->toBe('search_index');
});

it('sanitizes search queries safely for SQLite FTS5 syntax', function () {
    expect(SearchService::sanitizeQuery(''))->toBe('');
    expect(SearchService::sanitizeQuery('   '))->toBe('');

    // Single term -> prefix
    $s1 = SearchService::sanitizeQuery('saude');
    expect($s1)->toBe('"saude"*');

    // Multiple terms -> AND with last term prefix
    $s2 = SearchService::sanitizeQuery('saude coletiva');
    expect($s2)->toBe('"saude" AND "coletiva"*');

    // Preserves quoted phrase
    $s3 = SearchService::sanitizeQuery('"rede mesh" floresta');
    expect($s3)->toBe('"rede mesh" AND "floresta"*');

    // Strips dangerous characters
    $s4 = SearchService::sanitizeQuery('teste:123 (or) [not] {boom}');
    expect($s4)->toContain('"teste"');
    expect($s4)->not->toContain('[');
    expect($s4)->not->toContain(']');
});

it('indexes and searches posts with diacritic normalization and snippet highlight', function () {
    $page1 = Page::fromArray([
        'title' => 'Iniquidades Étnico-Raciais na Saúde',
        'slug' => 'artigos/iniquidades-saude',
        'kind' => 'article',
        'content' => 'Este relatório investiga a saúde materno-infantil em Santa Catarina com dados do SUS.',
        'metadata' => [
            'author' => 'Lumen Pink',
            'tags' => ['saude', 'epidemiologia'],
        ],
    ]);

    $page2 = Page::fromArray([
        'title' => 'Cultura Hacker e Cyberpunk',
        'slug' => 'notas/cyberpunk',
        'kind' => 'note',
        'content' => 'Explorando interfaces neon, computadores portáteis e redes descentralizadas.',
        'metadata' => [
            'author' => 'Lumen Pink',
            'tags' => ['cyberpunk', 'diy'],
        ],
    ]);

    expect($this->search->indexPage($page1))->toBeTrue();
    expect($this->search->indexPage($page2))->toBeTrue();

    // Search without accents ("saude" matches "saúde")
    $res = $this->search->search('saude');
    expect($res['total'])->toBe(1);
    expect($res['results'][0]['title'])->toBe('Iniquidades Étnico-Raciais na Saúde');
    expect($res['results'][0]['snippet'])->toContain('<mark>');
    expect($res['results'][0]['url'])->toBe('/artigos/iniquidades-saude/');

    // Search with prefix ("descentraliz*")
    $res2 = $this->search->search('descentraliz');
    expect($res2['total'])->toBe(1);
    expect($res2['results'][0]['title'])->toBe('Cultura Hacker e Cyberpunk');
});

it('distinguishes digital garden notes and applies type filtering', function () {
    $gardenPage = Page::fromArray([
        'title' => 'Telemetria Térmica Mesh',
        'slug' => 'garden/telemetria-mesh',
        'kind' => 'garden',
        'content' => 'Sistema de telemetria contínua via rede mesh com microcontroladores ESP32.',
        'metadata' => [
            'author' => 'Lumen',
            'tags' => ['mesh', 'iot'],
        ],
    ]);

    $postPage = Page::fromArray([
        'title' => 'Introdução às Redes Mesh',
        'slug' => 'artigos/redes-mesh',
        'kind' => 'article',
        'content' => 'Conceitos gerais de redes ad-hoc e protocolos descentralizados mesh.',
        'metadata' => [
            'author' => 'Lumen',
            'tags' => ['mesh'],
        ],
    ]);

    $this->search->indexPage($gardenPage);
    $this->search->indexPage($postPage);

    // Search all types
    $allRes = $this->search->search('mesh');
    expect($allRes['total'])->toBe(2);

    // Filter by type: garden only
    $gardenRes = $this->search->search('mesh', ['type' => 'garden']);
    expect($gardenRes['total'])->toBe(1);
    expect($gardenRes['results'][0]['type'])->toBe('garden');
    expect($gardenRes['results'][0]['doc_id'])->toBe('post:garden/telemetria-mesh');

    // Filter by type: article only
    $articleRes = $this->search->search('mesh', ['type' => 'article']);
    expect($articleRes['total'])->toBe(1);
    expect($articleRes['results'][0]['type'])->toBe('article');
});

it('indexes ingested Microsub feed entries and searches across feeds', function () {
    $feedEntry = [
        'uid' => 'entry-uuid-999',
        'name' => 'Novidades no protocolo IndieWeb',
        'url' => 'https://example.org/post/1',
        'author' => ['name' => 'Alice Hacker'],
        'published' => '2026-10-03T12:00:00Z',
        'content' => [
            'html' => '<p>Implementamos suporte a <strong>Webmentions</strong> e micropub hoje!</p>',
        ],
        'category' => ['indieweb', 'protocol'],
    ];

    expect($this->search->indexFeedEntry($feedEntry, 'inbox'))->toBeTrue();

    $res = $this->search->search('micropub');
    expect($res['total'])->toBe(1);
    expect($res['results'][0]['type'])->toBe('feed');
    expect($res['results'][0]['author'])->toBe('Alice Hacker');
    expect($res['results'][0]['snippet'])->toContain('<mark>');
});

it('rebuilds entire search index from pages collection', function () {
    $pages = new Pages();
    $pages->add(Page::fromArray([
        'title' => 'Primeiro Post',
        'slug' => 'artigos/primeiro',
        'kind' => 'article',
        'content' => 'Conteúdo do primeiro post com teste de busca.',
    ]));
    $pages->add(Page::fromArray([
        'title' => 'Segundo Post',
        'slug' => 'artigos/segundo',
        'kind' => 'article',
        'content' => 'Outro documento totalmente diferente.',
    ]));

    $stats = $this->search->rebuildIndex($pages);
    expect($stats['posts'])->toBe(2);

    $res = $this->search->search('primeiro');
    expect($res['total'])->toBe(1);
    expect($res['results'][0]['title'])->toBe('Primeiro Post');
});

it('executes SearchCommand in CLI mode with json output and reindex', function () {
    $page = Page::fromArray([
        'title' => 'Artigo de Filosofia da Tecnologia',
        'slug' => 'artigos/filosofia-tec',
        'kind' => 'article',
        'content' => 'Reflexões sobre a cibernética e a tecnopolítica moderna.',
    ]);
    $this->search->indexPage($page);

    $cmd = new SearchCommand($this->site);

    // Test text output
    ob_start();
    $exitCode = $cmd->execute(['indieinabox.php', 'search', 'cibernetica']);
    $output = ob_get_clean();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('Filosofia da Tecnologia');

    // Test JSON output
    ob_start();
    $exitCodeJson = $cmd->execute(['indieinabox.php', 'search', 'cibernetica', '--json']);
    $outputJson = ob_get_clean();

    expect($exitCodeJson)->toBe(0);
    $decoded = json_decode($outputJson, true);
    expect($decoded)->toBeArray();
    expect($decoded['total'])->toBe(1);
    expect($decoded['results'][0]['title'])->toBe('Artigo de Filosofia da Tecnologia');
});

it('handles search requests via SearchController for HTML and JSON', function () {
    $page = Page::fromArray([
        'title' => 'Manifesto Hacker',
        'slug' => 'artigos/manifesto-hacker',
        'kind' => 'article',
        'content' => 'Nosso crime é o da curiosidade.',
    ]);
    $this->search->indexPage($page);

    $controller = new \Indieinabox\Http\Controllers\SearchController($this->site, $this->search);

    // Test JSON endpoint
    $_GET['q'] = 'curiosidade';
    $_GET['format'] = 'json';
    ob_start();
    $controller->handle();
    $jsonOutput = ob_get_clean();

    $data = json_decode($jsonOutput, true);
    expect($data)->toBeArray();
    expect($data['total'])->toBe(1);
    expect($data['results'][0]['title'])->toBe('Manifesto Hacker');

    // Test HTML endpoint
    unset($_GET['format']);
    ob_start();
    $controller->handle();
    $htmlOutput = ob_get_clean();

    expect($htmlOutput)->toContain('<!DOCTYPE html>');
    expect($htmlOutput)->toContain('Manifesto Hacker');
    expect($htmlOutput)->toContain('<mark>');
});

