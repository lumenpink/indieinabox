<?php

declare(strict_types=1);

use Indieinabox\Page\Page;
use Indieinabox\Page\Pages;
use Indieinabox\Site\Paths;
use Indieinabox\Site\Site;
use Indieinabox\Support\FileUtils;
use Indieinabox\Versioning\VersionManager;
use Indieinabox\Versioning\VersionPublisher;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/iiab_version_test_' . uniqid();
    mkdir($this->tempDir, 0777, true);

    $this->contentDir = $this->tempDir . '/content';
    $this->outHtml = $this->tempDir . '/public';
    $this->outGemini = $this->tempDir . '/public_gemini';
    $this->outGopher = $this->tempDir . '/public_gopher';

    mkdir($this->contentDir, 0777, true);
    mkdir($this->outHtml, 0777, true);
    mkdir($this->outGemini, 0777, true);
    mkdir($this->outGopher, 0777, true);

    $paths = new Paths($this->tempDir);
    $paths->themeDir = dirname(__DIR__, 3) . '/resources';
    $paths->outputDirHtml = 'public';
    $paths->outputDirGemini = 'public_gemini';
    $paths->outputDirGopher = 'public_gopher';

    $this->site = new Site(null, $paths);
    $this->site->metadata->sitename = 'Wiki Test';
    $this->site->metadata->author = 'Lumen Pink';
    $this->site->metadata->fqdn = 'https://wiki.example.com';

    \Indieinabox\Core\Container::getInstance()->instance(Site::class, $this->site);
    $GLOBALS['site'] = $this->site;
});

afterEach(function () {
    FileUtils::recursiveRmdir($this->tempDir);
});

it('detects if page is versioned via frontmatter', function () {
    $p1 = new Page(null, null, null);
    $p1->metadata->versioned = true;
    expect(VersionManager::isVersioned($p1))->toBeTrue();

    $p2 = new Page(null, null, null);
    $p2->metadata->versioned = 'yes';
    expect(VersionManager::isVersioned($p2))->toBeTrue();

    $p3 = new Page(null, null, null);
    $p3->metadata->versioned = false;
    expect(VersionManager::isVersioned($p3))->toBeFalse();

    $p4 = new Page(null, null, null);
    expect(VersionManager::isVersioned($p4))->toBeFalse();
});

it('resolves companion .history.json file path', function () {
    $page = new Page(null, null, null);
    $page->filepath = '/vault/content/manifesto.md';

    $companion = VersionManager::getCompanionPath($page);
    expect($companion)->toBe('/vault/content/manifesto.history.json');
});

it('creates initial version and tracks updates without modifying markdown file', function () {
    $mdFile = $this->contentDir . '/manifesto.md';
    $companionFile = $this->contentDir . '/manifesto.history.json';

    file_put_contents($mdFile, "---\ntitle: Manifesto\nversioned: true\n---\nConteúdo original da v1.");

    $page = new Page(null, null, null);
    $page->slug = 'manifesto';
    $page->filepath = $mdFile;
    $page->title = 'Manifesto';
    $page->rawBody = "Conteúdo original da v1.";
    $page->metadata->versioned = true;
    $page->metadata->version_note = "Versão inicial do manifesto";

    // Fixed timestamp: 2026-10-02 10:00:00 UTC
    $ts1 = 1790935200;
    $history1 = VersionManager::processPage($page, $ts1);

    expect(file_exists($companionFile))->toBeTrue();
    expect($history1['current_version'])->toBe('v1.20261002.1');
    expect(count($history1['versions']))->toBe(1);
    expect($history1['versions'][0]['note'])->toBe('Versão inicial do manifesto');
    expect($page->metadata->version)->toBe('v1.20261002.1');
    expect($page->metadata->version_count)->toBe(1);

    // Verify markdown file was NOT modified (zero friction in Obsidian)
    $originalMd = file_get_contents($mdFile);
    expect($originalMd)->toBe("---\ntitle: Manifesto\nversioned: true\n---\nConteúdo original da v1.");

    // Run again with SAME content - should NOT create new version
    $historyNoChange = VersionManager::processPage($page, $ts1 + 300);
    expect($historyNoChange['current_version'])->toBe('v1.20261002.1');
    expect(count($historyNoChange['versions']))->toBe(1);

    // Edit content via Obsidian
    $page->rawBody = "Conteúdo original da v1.\nNova seção adicionada via Obsidian.";
    $page->metadata->version_note = "Adicionando nova seção";
    $ts2 = $ts1 + 3600; // 1 hour later, same day

    $history2 = VersionManager::processPage($page, $ts2);
    expect($history2['current_version'])->toBe('v2.20261002.2');
    expect(count($history2['versions']))->toBe(2);
    expect($history2['versions'][1]['diff'])->toContain('+Nova seção adicionada via Obsidian.');
    expect($history2['versions'][1]['bytes_diff'])->toBeGreaterThan(0);
    expect($page->metadata->version)->toBe('v2.20261002.2');
    expect($page->metadata->version_count)->toBe(2);
});

it('publishes 100% static HTML, Gemini and Gopher history and snapshots in separate directories', function () {
    $mdFile = $this->contentDir . '/garden.md';
    file_put_contents($mdFile, "Jardim digital.");

    $page = new Page(null, null, null);
    $page->slug = 'garden';
    $page->filepath = $mdFile;
    $page->title = 'Jardim Digital';
    $page->rawBody = "Jardim digital v1.";
    $page->metadata->versioned = true;
    $page->metadata->layout = 'page';
    $page->metadata->tags = [];

    $ts1 = 1790935200;
    VersionManager::processPage($page, $ts1);

    // Update to v2
    $page->rawBody = "Jardim digital v1.\nExpansão do canteiro de flores.";
    $ts2 = $ts1 + 3600;
    $history = VersionManager::processPage($page, $ts2);

    $pages = new Pages([$page]);

    VersionPublisher::publishAll($page, $this->site, $pages, $history);

    // 1. Verify HTML in public/
    $htmlHistory = $this->outHtml . '/garden/history/index.html';
    $htmlV1 = $this->outHtml . '/garden/v/v1.20261002.1/index.html';
    $htmlV2 = $this->outHtml . '/garden/v/v2.20261002.2/index.html';

    expect(file_exists($htmlHistory))->toBeTrue();
    expect(file_exists($htmlV1))->toBeTrue();
    expect(file_exists($htmlV2))->toBeTrue();

    $historyContent = file_get_contents($htmlHistory);
    expect($historyContent)->toContain('Histórico de revisões: Jardim Digital');
    expect($historyContent)->toContain('v2.20261002.2');
    expect($historyContent)->toContain('v1.20261002.1');
    expect($historyContent)->toContain('<table class="diff wiki-diff"');
    expect($historyContent)->toContain('Comparar revisões selecionadas');
    // Ensure client JS is embedded for 100% offline static diffing
    expect($historyContent)->toContain('lcsDiffOps');

    // Verify snapshot has old version warning
    $v1Content = file_get_contents($htmlV1);
    expect($v1Content)->toContain('wiki-revision-notice');
    expect($v1Content)->toContain('Esta é uma versão antiga desta página');

    // 2. Verify Gemini in separate public_gemini/
    $gmiHistory = $this->outGemini . '/garden/history/index.gmi';
    $gmiV1 = $this->outGemini . '/garden/v/v1.20261002.1/index.gmi';
    expect(file_exists($gmiHistory))->toBeTrue();
    expect(file_exists($gmiV1))->toBeTrue();

    $gmiContent = file_get_contents($gmiHistory);
    expect($gmiContent)->toContain('# Histórico de Revisões: Jardim Digital');
    expect($gmiContent)->toContain('=> ../v/v1.20261002.1/');

    // 3. Verify Gopher in separate public_gopher/
    $gopherHistory = $this->outGopher . '/garden/history/gophermap';
    $gopherV1 = $this->outGopher . '/garden/v/v1.20261002.1/gophermap';
    expect(file_exists($gopherHistory))->toBeTrue();
    expect(file_exists($gopherV1))->toBeTrue();

    $gopherContent = file_get_contents($gopherHistory);
    expect($gopherContent)->toContain('Histórico de Revisões: Jardim Digital');
    expect($gopherContent)->toContain('garden/v/v1.20261002.1/');
});

it('integrates seamlessly with PagePublisher and leaves unversioned posts intact', function () {
    $interactionRepo = new \Indieinabox\Repositories\FileInteractionRepository();
    $pages = new Pages([]);
    $publisher = new \Indieinabox\SiteBuilder\PagePublisher($this->site, $pages, null, $interactionRepo);

    // 1. Unversioned page
    $unversionedMd = $this->contentDir . '/plain.md';
    file_put_contents($unversionedMd, "Texto sem versionamento.");
    $unversionedPage = new Page(null, null, null);
    $unversionedPage->slug = 'plain';
    $unversionedPage->filepath = $unversionedMd;
    $unversionedPage->title = 'Plain Post';
    $unversionedPage->rawBody = "Texto sem versionamento.";
    $unversionedPage->metadata->layout = 'page';
    $unversionedPage->metadata->tags = [];

    $publisher->publish($unversionedPage);

    expect(file_exists($this->outHtml . '/plain/index.html'))->toBeTrue();
    expect(file_exists($this->outHtml . '/plain/history/index.html'))->toBeFalse();
    expect(file_exists($this->contentDir . '/plain.history.json'))->toBeFalse();

    // 2. Versioned page
    $versionedMd = $this->contentDir . '/wiki.md';
    file_put_contents($versionedMd, "Artigo da wiki.");
    $versionedPage = new Page(null, null, null);
    $versionedPage->slug = 'wiki';
    $versionedPage->filepath = $versionedMd;
    $versionedPage->title = 'Artigo Wiki';
    $versionedPage->rawBody = "Artigo da wiki.";
    $versionedPage->metadata->versioned = true;
    $versionedPage->metadata->layout = 'page';
    $versionedPage->metadata->tags = [];

    $publisher->publish($versionedPage);

    expect(file_exists($this->outHtml . '/wiki/index.html'))->toBeTrue();
    expect(file_exists($this->outHtml . '/wiki/history/index.html'))->toBeTrue();
    expect(file_exists($this->contentDir . '/wiki.history.json'))->toBeTrue();
    expect(file_exists($this->outGemini . '/wiki/history/index.gmi'))->toBeTrue();
    expect(file_exists($this->outGopher . '/wiki/history/gophermap'))->toBeTrue();

    $html = file_get_contents($this->outHtml . '/wiki/index.html');
    expect($html)->toContain('meta-version');
    expect($html)->toContain('v1.');
    expect($html)->toContain('wiki/history/');
});
