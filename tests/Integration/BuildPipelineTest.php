<?php

declare(strict_types=1);

use Indieinabox\Yaml;

// Setup directories
$sandbox = dirname(__DIR__) . '/Integration/tmp_sandbox';
$outputDir = $sandbox . '/public';

function cleanSandbox(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() && !$fileinfo->isLink()) ? 'rmdir' : 'unlink';
        @$todo($fileinfo->getPathname());
    }
    @rmdir($dir);
}

beforeEach(function () use ($sandbox) {
    cleanSandbox($sandbox);
    mkdir($sandbox, 0777, true);
    mkdir($sandbox . '/content', 0777, true);
    mkdir($sandbox . '/resources', 0777, true);
    mkdir($sandbox . '/resources/views', 0777, true);
    mkdir($sandbox . '/resources/views/livejs', 0777, true);
    mkdir($sandbox . '/resources/static', 0777, true);
});

afterEach(function () use ($sandbox) {
    cleanSandbox($sandbox);
});

it('executes the build pipeline and generates the static site correctly', function () use ($sandbox, $outputDir) {
    // 1. Create config.yml
    $config = [
        'base' => 'testbase',
        'title' => 'Integration Site',
        'sitename' => 'My Integration Site',
        'author' => 'Agent Antigravity',
        'fqdn' => 'https://example.com/testbase',
        'outputdir' => 'public',
        'contentdir' => 'content',
        'lang' => 'en',
        'htmlpostprocessing' => 'beautify'
    ];
    $yaml = new Yaml();
    file_put_contents($sandbox . '/config.yml', $yaml->dump($config));

    // 2. Create markdown content
    file_put_contents(
        $sandbox . '/content/index.md',
        "---\ntitle: Home Page\nlayout: page\n---\nWelcome home! #welcome"
    );
    file_put_contents($sandbox . '/content/about.md', "---\ntitle: About Page\nlayout: page\n---\nAbout me.");

    // 3. Create views
    file_put_contents($sandbox . '/resources/views/page.php', <<<PHP
<!DOCTYPE html>
<html>
<head>
    <title><?= \$page->title ?></title>
</head>
<body>
    <h1><?= \$page->title ?></h1>
    <article><?= \$page->content ?></article>
</body>
</html>
PHP
    );

    file_put_contents($sandbox . '/resources/views/feed.php', <<<PHP
<?php
ob_start();
echo '<?xml version="1.0" encoding="utf-8"?>';
?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title><?= \$site->metadata->sitename ?></title>
  <link href="<?= \$site->metadata->fqdn ?>"/>
  <updated><?= date(DATE_ATOM) ?></updated>
  <author>
    <name><?= \$site->metadata->author ?></name>
  </author>
  <id><?= \$site->metadata->fqdn ?></id>
  <?php foreach (\$pages as \$p): ?>
  <entry>
    <title><?= \$p->title ?></title>
    <link href="<?= \$site->metadata->fqdn . \$p->slug ?>"/>
    <id><?= \$site->metadata->fqdn . \$p->slug ?></id>
    <updated><?= date(DATE_ATOM) ?></updated>
    <content type="html"><?= htmlspecialchars((string)\$p->content) ?></content>
  </entry>
  <?php endforeach; ?>
</feed>
<?php
\$feedContent = ob_get_clean();
file_put_contents(\$base . DIRECTORY_SEPARATOR . \$site->outputdir . DIRECTORY_SEPARATOR . 'feed.xml', \$feedContent);
PHP
    );

    // 4. Create static asset and live.js symlink
    file_put_contents($sandbox . '/resources/static/app.css', 'body { color: red; }');
    symlink(
        dirname(dirname(__DIR__)) . '/resources/views/livejs/live.js',
        $sandbox . '/resources/views/livejs/live.js'
    );

    // 5. Setup symlinks to app, bootstrap, data, vendor
    symlink(dirname(dirname(__DIR__)) . '/vendor', $sandbox . '/vendor');
    
    $isCompiled = getenv('TEST_COMPILED') === 'true' || getenv('TEST_COMPILED') === '1';
    
    if ($isCompiled) {
        // Test compiled build runner
        copy(dirname(dirname(__DIR__)) . '/indieinabox.php', $sandbox . '/build.php');
        // Compiled script needs data directory to load global dictionary arrays
        symlink(dirname(dirname(__DIR__)) . '/data', $sandbox . '/data');
    } else {
        // Test source runner
        copy(dirname(dirname(__DIR__)) . '/build.php', $sandbox . '/build.php');
        symlink(dirname(dirname(__DIR__)) . '/bootstrap', $sandbox . '/bootstrap');
        symlink(dirname(dirname(__DIR__)) . '/app', $sandbox . '/app');
        symlink(dirname(dirname(__DIR__)) . '/data', $sandbox . '/data');
    }

    // 6. Run the build pipeline
    $cmd = 'php ' . escapeshellarg($sandbox . '/build.php');
    $output = shell_exec($cmd);

    // 7. Verify build results
    expect(is_dir($outputDir))->toBeTrue();
    expect(is_file($outputDir . '/index.html'))->toBeTrue();
    expect(is_file($outputDir . '/about/index.html'))->toBeTrue();
    expect(is_file($outputDir . '/feed.xml'))->toBeTrue();
    expect(is_file($outputDir . '/app.css'))->toBeTrue(); // copied from resources/static

    $indexHtml = file_get_contents($outputDir . '/index.html');
    expect($indexHtml)->toContain('<title>Home Page</title>');
    expect($indexHtml)->toContain('<h1>Home Page</h1>');
    expect($indexHtml)->toContain('Welcome home!');
    expect($indexHtml)->not->toContain('live.js'); // Not in dev mode

    $aboutHtml = file_get_contents($outputDir . '/about/index.html');
    expect($aboutHtml)->toContain('<title>About Page</title>');
    expect($aboutHtml)->toContain('About me.');

    $feedXml = file_get_contents($outputDir . '/feed.xml');
    expect($feedXml)->toContain('<title>My Integration Site</title>');
    expect($feedXml)->toContain('<name>Agent Antigravity</name>');
    expect($feedXml)->toContain('<entry>');

    $cssContent = file_get_contents($outputDir . '/app.css');
    expect($cssContent)->toContain('body { color: red; }');
});

it('injects live-reload script when building with -d (dev mode)', function () use ($sandbox, $outputDir) {
    // 1. Create config.yml
    $config = [
        'base' => 'testbase',
        'title' => 'Integration Site',
        'sitename' => 'My Integration Site',
        'author' => 'Agent Antigravity',
        'fqdn' => 'https://example.com/testbase',
        'outputdir' => 'public',
        'contentdir' => 'content',
        'lang' => 'en',
        'htmlpostprocessing' => 'beautify'
    ];
    $yaml = new Yaml();
    file_put_contents($sandbox . '/config.yml', $yaml->dump($config));

    // 2. Create markdown content
    file_put_contents($sandbox . '/content/index.md', "---\ntitle: Home Page\nlayout: page\n---\nWelcome home!");

    // 3. Create views with livejs folder and live.js symlink
    file_put_contents($sandbox . '/resources/views/page.php', <<<PHP
<!DOCTYPE html>
<html>
<head>
    <title><?= \$page->title ?></title>
    <?php if (\$site->dev): ?>
        <script src="<?= \$site->paths->baseDir ?>/js/live.js"></script>
    <?php endif; ?>
</head>
<body>
    <h1><?= \$page->title ?></h1>
    <article><?= \$page->content ?></article>
</body>
</html>
PHP
    );
    symlink(
        dirname(dirname(__DIR__)) . '/resources/views/livejs/live.js',
        $sandbox . '/resources/views/livejs/live.js'
    );

    // 4. Setup symlinks to app, bootstrap, data, vendor
    symlink(dirname(dirname(__DIR__)) . '/vendor', $sandbox . '/vendor');
    
    $isCompiled = getenv('TEST_COMPILED') === 'true' || getenv('TEST_COMPILED') === '1';
    
    if ($isCompiled) {
        copy(dirname(dirname(__DIR__)) . '/indieinabox.php', $sandbox . '/build.php');
        symlink(dirname(dirname(__DIR__)) . '/data', $sandbox . '/data');
    } else {
        copy(dirname(dirname(__DIR__)) . '/build.php', $sandbox . '/build.php');
        symlink(dirname(dirname(__DIR__)) . '/bootstrap', $sandbox . '/bootstrap');
        symlink(dirname(dirname(__DIR__)) . '/app', $sandbox . '/app');
        symlink(dirname(dirname(__DIR__)) . '/data', $sandbox . '/data');
    }

    // 5. Run build pipeline with -d (dev mode option)
    $cmd = 'php ' . escapeshellarg($sandbox . '/build.php') . ' -d';
    $output = shell_exec($cmd);

    // 6. Verify output
    expect(is_file($outputDir . '/index.html'))->toBeTrue();
    expect(is_file($outputDir . '/js/live.js'))->toBeTrue(); // Copied from livejs view

    $indexHtml = file_get_contents($outputDir . '/index.html');
    // Check live.js injection in <head> tag
    expect($indexHtml)->toContain('live.js');
});
