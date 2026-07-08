<?php
declare(strict_types=1);

use Indieinabox\SiteBuilder;
use Indieinabox\Site;
use Indieinabox\Site\Paths;

beforeEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    $this->tempDir = sys_get_temp_dir() . '/indieinabox_garden_tags_test_' . uniqid();
    mkdir($this->tempDir);
    mkdir($this->tempDir . '/content', 0777, true);
    mkdir($this->tempDir . '/content/garden', 0777, true);
    
    // Create necessary views for rendering summary and page
    mkdir($this->tempDir . '/resources/views/includes', 0777, true);
    
    // We need the REAL summary.php and page.php since we're testing its logic
    copy(__DIR__ . '/../../resources/views/includes/summary.php', $this->tempDir . '/resources/views/includes/summary.php');
    copy(__DIR__ . '/../../resources/views/page.php', $this->tempDir . '/resources/views/page.php');

    $this->paths = new Paths($this->tempDir, 'public_html', 'public_gemini', 'public_gopher', 'public_media', 'content', 'resources');
    
    global $site;
    $site = new Site(null, $this->paths);
    $site->config['kinds'] = [
        'garden' => ['content_dir' => 'garden']
    ];
    $site->options->prettylinks = true;
    
    $this->site = $site;
});

afterEach(function () {
    /** @var \Tests\TestCase|mixed $this */
    \Indieinabox\Helper::recursiveRmdir($this->tempDir);
});

test('garden tags are rendered and translatable in the HTML output', function () {
    /** @var \Tests\TestCase|mixed $this */
    file_put_contents($this->tempDir . '/content/garden/test.md', "---\nkind: garden\ntitle: My Digital Garden\n---\nHello");

    // Let's configure translations
    $this->site->localization->defaultLang = 'en';
    $this->site->localization->lang = ['en', 'pt'];
    $this->site->config['translations'] = [
        'Flowerbed' => ['pt' => 'Canteiro'],
        'Confidence' => ['pt' => 'Confiança'],
        'possible' => ['pt' => 'possível']
    ];

    $builder = new SiteBuilder($this->site);
    $builder->build();
    
    $htmlEn = file_get_contents($this->tempDir . '/public_html/garden/test/index.html');
    
    expect($htmlEn)->toContain('Flowerbed');
    expect($htmlEn)->toContain('Confidence');
    expect($htmlEn)->toContain('possible');
    expect($htmlEn)->toContain('sprout');
    expect($htmlEn)->toContain('trivial');

    $htmlPt = file_get_contents($this->tempDir . '/public_html/pt/garden/test/index.html');
    
    expect($htmlPt)->toContain('Canteiro');
    expect($htmlPt)->toContain('Confiança');
    expect($htmlPt)->toContain('possível');
});
