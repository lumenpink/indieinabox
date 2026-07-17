<?php

declare(strict_types=1);

use Indieinabox\Yaml;

$integrationSandbox = __DIR__ . '/tmp_integration_sandbox';

function cleanIntegrationSandbox(string $dir): void
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

function getFreeIntegrationPort(int $startPort = 9100): int
{
    for ($port = $startPort; $port < $startPort + 100; $port++) {
        $socket = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
        if ($socket !== false) {
            fclose($socket);
            return $port;
        }
    }
    return $startPort;
}

beforeEach(function () use ($integrationSandbox) {
    cleanIntegrationSandbox($integrationSandbox);
    mkdir($integrationSandbox, 0777, true);
    mkdir($integrationSandbox . '/content', 0777, true);
    mkdir($integrationSandbox . '/resources', 0777, true);
    mkdir($integrationSandbox . '/resources/views', 0777, true);
    mkdir($integrationSandbox . '/resources/static', 0777, true);
});

afterEach(function () use ($integrationSandbox) {
    cleanIntegrationSandbox($integrationSandbox);
});

it(
    'compiles the app and runs both CLI build and Web routing from the single-file entry',
    function () use ($integrationSandbox) {
    $root = dirname(dirname(__DIR__));

    // 1. Recompile indieinabox.php
    $compileCmd = 'php ' . escapeshellarg($root . '/compile.php');
    shell_exec($compileCmd);

    expect(file_exists($root . '/indieinabox.php'))->toBeTrue();

    // 2. Set up sandbox content and configuration
    // Initialize database for compiled script
    file_put_contents(
        $integrationSandbox . '/.config.php',
        "<?php\nreturn ['data_dir' => '" . $integrationSandbox . "'];\n"
    );
    $db = new \PDO('sqlite:' . $integrationSandbox . '/.indieinabox.sqlite');
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $db->exec(file_get_contents($root . '/database.sql'));

    $stmt = $db->prepare('UPDATE settings SET value = :value WHERE key = :key');
    $stmt->execute([':key' => 'base', ':value' => '']);
    $stmt->execute([':key' => 'title', ':value' => 'Integration Test Title']);
    $stmt->execute([':key' => 'sitename', ':value' => 'Integration Test Site']);
    $stmt->execute([':key' => 'author', ':value' => 'Antigravity']);
    $stmt->execute([':key' => 'fqdn', ':value' => 'https://example.com']);
    $stmt->execute([':key' => 'outputdir', ':value' => 'public']);
    $stmt->execute([':key' => 'contentdir', ':value' => 'content']);
    $stmt->execute([':key' => 'themedir', ':value' => 'resources']);
    $stmt->execute([':key' => 'lang', ':value' => '["en"]']);
    $db = null;

    // Simple markdown content
    file_put_contents(
        $integrationSandbox . '/content/index.md',
        "---\ntitle: Home Page\nlayout: page\n---\nWelcome home!"
    );
    file_put_contents(
        $integrationSandbox . '/content/about.md',
        "---\ntitle: About Page\nlayout: page\n---\nAbout page content."
    );

    file_put_contents(
        $integrationSandbox . '/resources/views/page.php',
        <<<PHP
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
    file_put_contents(
        $integrationSandbox . '/resources/views/home.php',
        <<<PHP
<!DOCTYPE html>
<html>
<head>
    <title><?= \$page->title ?> | <?= \$site->metadata->sitename ?></title>
</head>
<body>
    <h1><?= \$page->title ?></h1>
    <article><?= \$page->content ?></article>
</body>
</html>
PHP
    );


    // 3. Symlink dependencies into sandbox and copy compiled single-file script as build.php
    symlink($root . '/vendor', $integrationSandbox . '/vendor');
    mkdir($integrationSandbox . '/data', 0777, true);
    foreach (scandir($root . '/data') as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $sourcePath = $root . '/data/' . $item;
        if (!is_dir($sourcePath)) {
            symlink($sourcePath, $integrationSandbox . '/data/' . $item);
        }
    }
    copy($root . '/indieinabox.php', $integrationSandbox . '/indieinabox.php');

    // 4. Test CLI Mode: Run build process
    $cmd = 'php ' . escapeshellarg($integrationSandbox . '/indieinabox.php') . ' -c ' . escapeshellarg($integrationSandbox . '/.config.php') . ' 2>&1';
    $output = shell_exec($cmd);
    echo "CLI OUTPUT:\n" . $output . "\n";
    if (!file_exists($integrationSandbox . '/public_html/about/index.html')) {
        echo "ABOUT.MD output:\n" . $output . "\n";
    }
    expect(is_dir($integrationSandbox . '/public_html'))->toBeTrue();
    expect(file_exists($integrationSandbox . '/public_html/index.html'))->toBeTrue();
    expect(file_exists($integrationSandbox . '/public_html/about/index.html'))->toBeTrue();

    $indexHtml = file_get_contents($integrationSandbox . '/public_html/index.html');
    expect($indexHtml)->toContain('<title>Home Page | Integration Test Site</title>')
        ->and($indexHtml)->toContain('Welcome home!');

    // 5. Test Web Mode: Run PHP built-in servers
    $port1 = getFreeIntegrationPort(9100);
    $host1 = "127.0.0.1:$port1";

    $port2 = getFreeIntegrationPort(9200);
    $host2 = "127.0.0.1:$port2";

    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];

    // Server 1 (Router for the App)
    $process1 = null;
    $pipes1 = null;
    $srvCmd1 = "exec php -S $host1 " . escapeshellarg($integrationSandbox . '/indieinabox.php');
    $process1 = proc_open($srvCmd1, $descriptorspec, $pipes1);

    // Server 2 (Static server for the source link to avoid deadlock)
    $process2 = null;
    $pipes2 = null;
    $srvCmd2 = "exec php -S $host2 -t " . escapeshellarg($integrationSandbox . '/public_html');
    $process2 = proc_open($srvCmd2, $descriptorspec, $pipes2);

    // Wait 250ms for servers to start
    usleep(250000);

    try {
        // A. Verify GET help page endpoint
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'ignore_errors' => true
            ]
        ]);
        $getResponse = file_get_contents("http://$host1/webmention", false, $context);

        expect($getResponse)->toContain('Webmention Endpoint');

        // B. Verify POST webmention endpoint with target validation
        // Mock a source page linking to target page on server 2
        $sourceContent = '<html><body><a href="https://example.com/about">Linked!</a></body></html>';
        file_put_contents($integrationSandbox . '/public_html/source_post.html', $sourceContent);

        $sourceUrl = "http://$host2/source_post.html";
        $targetUrl = "https://example.com/about";

        // POST request options
        $postData = http_build_query([
            'source' => $sourceUrl,
            'target' => $targetUrl
        ]);

        $postOpts = [
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-type: application/x-www-form-urlencoded\r\n",
                'content'       => $postData,
                'timeout'       => 3,
                'ignore_errors' => true
            ]
        ];
        $postContext = stream_context_create($postOpts);
        $postResponse = file_get_contents("http://$host1/webmention", false, $postContext);

        $json = json_decode($postResponse, true);
        if ($json['status'] !== 202) {
            echo "Webmention failed: " . print_r($json, true) . "\n";
        }
        expect($json)->toBeArray()
            ->and($json['status'])->toBe(202)
            ->and($json['message'])->toContain('Webmention accepted');

        // Run background worker to process the queued webmention
        $cronCmd = 'cd ' . escapeshellarg($integrationSandbox) . ' && php indieinabox.php cron 2>&1';
        $cronOut = shell_exec($cronCmd);

        // Check that webmention data is created correctly in Markdown
        $hash = md5('about');
        $expectedId = $hash . '_' . md5($sourceUrl);
        $mdFile = $integrationSandbox . '/microsub/inbox/notifications/' . $expectedId . '.md';
        
        if (!file_exists($mdFile)) {
            echo "Cron output:\n" . $cronOut . "\n";
            echo "Expected mdFile: " . $mdFile . "\n";
        }
        
        expect(file_exists($mdFile))->toBeTrue();

        $content = file_get_contents($mdFile);
        preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches);
        $yamlParser = new Yaml();
        $savedData = $yamlParser->loadString($matches[1]);
        
        expect($savedData['source'])->toBe($sourceUrl);
        expect($savedData['target'])->toBe($targetUrl);
    } finally {
        // Terminate background web server processes
        if (isset($pipes1)) {
            foreach ($pipes1 as $pipe) {
                if (is_resource($pipe)) fclose($pipe);
            }
        }
        if (is_resource($process1)) {
            proc_terminate($process1, 9);
            proc_close($process1);
        }
        
        if (isset($pipes2)) {
            foreach ($pipes2 as $pipe) {
                if (is_resource($pipe)) fclose($pipe);
            }
        }
        if (is_resource($process2)) {
            proc_terminate($process2, 9);
            proc_close($process2);
        }
    }
}
);
