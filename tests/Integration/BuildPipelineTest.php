<?php

declare(strict_types=1);

use Indieinabox\Yaml;

// Setup directories
$sandbox = dirname(__DIR__) . '/Integration/tmp_sandbox';
$outputDir = $sandbox . '/public_html';

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

/**
 * Writes the default config.yml to the sandbox.
 */
function writeSandboxConfig(string $sandbox): void
{
    // Write database config to bypass installer logic
    file_put_contents($sandbox . '/.config.php', "<?php\nreturn ['data_dir' => '" . $sandbox . "'];\n");
    // Initialize the SQLite DB with the schema
    $db = new \PDO('sqlite:' . $sandbox . '/.indieinabox.sqlite');
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $db->exec(file_get_contents(dirname(__DIR__, 2) . '/database.sql'));
    
    // Configure settings for the test
    $stmt = $db->prepare('UPDATE settings SET value = :value WHERE key = :key');
    $stmt->execute([':key' => 'base', ':value' => 'testbase']);
    $stmt->execute([':key' => 'title', ':value' => 'Integration Site']);
    $stmt->execute([':key' => 'sitename', ':value' => 'My Integration Site']);
    $stmt->execute([':key' => 'author', ':value' => 'Agent Antigravity']);
    $stmt->execute([':key' => 'fqdn', ':value' => 'https://example.com/testbase']);
    $stmt->execute([':key' => 'outputdir', ':value' => 'public']);
    $stmt->execute([':key' => 'contentdir', ':value' => 'content']);
    $stmt->execute([':key' => 'themedir', ':value' => 'resources']);
    $stmt->execute([':key' => 'lang', ':value' => '["en"]']);
    $stmt->execute([':key' => 'htmlpostprocessing', ':value' => 'beautify']);
    $db = null;
}

/**
 * Creates the minimal page.php view (no live.js support) in the sandbox.
 */
function writeBasePageView(string $sandbox): void
{
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
    file_put_contents($sandbox . '/resources/views/index_page.php', <<<PHP
<!DOCTYPE html>
<html>
<head>
    <title><?= \$page->title ?> | <?= \$site->metadata->title ?></title>
</head>
<body>
    <h1><?= \$page->title ?></h1>
    <article><?= \$page->content ?></article>
</body>
</html>
PHP
    );
    file_put_contents($sandbox . '/resources/views/home.php', <<<PHP
<!DOCTYPE html>
<html>
<head>
    <title><?= \$page->title ?> | <?= \$site->metadata->title ?></title>
</head>
<body>
    <h1><?= \$page->title ?></h1>
    <article><?= \$page->content ?></article>
</body>
</html>
PHP
    );
}



/**
 * Sets up vendor/app/bootstrap/data symlinks and copies the build script
 * into the sandbox, respecting the TEST_COMPILED environment variable.
 */
function setupSandboxRunner(string $sandbox): void
{
    $root        = dirname(dirname(__DIR__));
    $isCompiled  = getenv('TEST_COMPILED') === 'true' || getenv('TEST_COMPILED') === '1';

    symlink($root . '/vendor', $sandbox . '/vendor');

    if ($isCompiled) {
        copy($root . '/indieinabox.php', $sandbox . '/build.php');
        symlink($root . '/data', $sandbox . '/data');
    } else {
        copy($root . '/build.php', $sandbox . '/build.php');
        exec("cp -r " . escapeshellarg($root . '/bootstrap') . " " . escapeshellarg($sandbox . '/bootstrap'));
        exec("cp -r " . escapeshellarg($root . '/app') . " " . escapeshellarg($sandbox . '/app'));
        symlink($root . '/data', $sandbox . '/data');
    }
}

beforeEach(function () use ($sandbox) {
    cleanSandbox($sandbox);
    mkdir($sandbox, 0777, true);
    mkdir($sandbox . '/content', 0777, true);
    mkdir($sandbox . '/resources', 0777, true);
    mkdir($sandbox . '/resources/views', 0777, true);
    mkdir($sandbox . '/resources/static', 0777, true);
});

afterEach(function () use ($sandbox) {
    cleanSandbox($sandbox);
});

it('executes the build pipeline and generates the static site correctly', function () use ($sandbox, $outputDir) {
    // 1. Create config.yml
    writeSandboxConfig($sandbox);

    // 2. Create markdown content
    file_put_contents($sandbox . '/content/index.md', "---\ntitle: Home Page\nlayout: page\n---\nWelcome home! #welcome");
    file_put_contents($sandbox . '/content/about.md', "---\ntitle: About Page\nlayout: page\n---\nAbout me.");
    mkdir($sandbox . '/content/articles');
    file_put_contents($sandbox . '/content/articles/first-post.md', "---\ntitle: My First Post\nlayout: article\ndate: 2026-07-17\n---\nThis is my first post.");

    // 3. Create views
    writeBasePageView($sandbox);

    file_put_contents(
        $sandbox . '/resources/views/feed.php',
        <<<PHP
<?php
echo "FEED_PHP_EXECUTED\n";
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
file_put_contents(\$base . DIRECTORY_SEPARATOR . \$site->paths->outputDirHtml . DIRECTORY_SEPARATOR . 'feed.xml', \$feedContent);
PHP
    );

    file_put_contents($sandbox . '/resources/static/app.css', 'body { color: red; }');

    // 5. Setup symlinks to app, bootstrap, data, vendor
    setupSandboxRunner($sandbox);

    // 6. Run the build pipeline
    $cmd = 'php ' . escapeshellarg($sandbox . '/build.php');
    $output = shell_exec($cmd);

    // 7. Verify build results
    if (!is_dir($outputDir)) {
        echo "BUILD PHP OUTPUT: \n" . $output . "\n";
    }
    expect(is_dir($outputDir))->toBeTrue();
    expect(is_file($outputDir . '/index.html'))->toBeTrue();
    expect(is_file($outputDir . '/about/index.html'))->toBeTrue();
    if (!is_file($outputDir . '/rss.xml')) {
        var_dump(scandir($outputDir));
    }
    expect(is_file($outputDir . '/rss.xml'))->toBeTrue();
    expect(is_file($outputDir . '/atom.xml'))->toBeTrue();
    expect(is_file($outputDir . '/app.css'))->toBeTrue(); // copied from theme/static

    $indexHtml = file_get_contents($outputDir . '/index.html');
    echo "INDEX HTML:\n$indexHtml\n";
    expect($indexHtml)->toContain('<title>Home Page | Integration Site</title>');
    expect($indexHtml)->toContain('<h1>Home Page</h1>');
    expect($indexHtml)->toContain('Welcome home!');
    expect($indexHtml)->not->toContain('live.js'); // Not in dev mode

    $aboutHtml = file_get_contents($outputDir . '/about/index.html');
    expect($aboutHtml)->toContain('<title>About Page</title>');
    expect($aboutHtml)->toContain('About me.');

    $rssXml = file_get_contents($outputDir . '/rss.xml');
    expect($rssXml)->toContain('<title>My Integration Site</title>');
    expect($rssXml)->toContain('<item>');

    $cssContent = file_get_contents($outputDir . '/app.css');
    expect($cssContent)->toContain('body { color: red; }');
});


