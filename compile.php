<?php

declare(strict_types=1);

$base = __DIR__;

// 1. Get all PHP files in app/ recursively
function getPhpFiles(string $dir): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

$appFiles = getPhpFiles($base . '/app');

// Prepare the compiled code
$compiled = "<?php\n\ndeclare(strict_types=1);\n\n";

$compiled .= "namespace {\n";
$compiled .= "    if (PHP_VERSION_ID < 80200) {\n";
$compiled .= "        \$errorMessage = \"Error: IndieInABox requires PHP version 8.2.0 or higher. Your current PHP version is \" . PHP_VERSION . \". Please upgrade your PHP installation.\";\n";
$compiled .= "        if (PHP_SAPI === 'cli') {\n";
$compiled .= "            file_put_contents('php://stderr', \"\\033[31;1m\" . \$errorMessage . \"\\033[0m\\n\");\n";
$compiled .= "            exit(1);\n";
$compiled .= "        } else {\n";
$compiled .= "            header('HTTP/1.1 500 Internal Server Error');\n";
$compiled .= "            header('Content-Type: text/html; charset=utf-8');\n";
$compiled .= "            echo \"<!DOCTYPE html>\n";
$compiled .= "<html>\n";
$compiled .= "<head>\n";
$compiled .= "    <title>PHP Version Error</title>\n";
$compiled .= "    <style>\n";
$compiled .= "        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f7fafc; color: #2d3748; padding: 2rem; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }\n";
$compiled .= "        .card { background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); max-width: 500px; width: 100%; border-top: 4px solid #e53e3e; }\n";
$compiled .= "        h1 { color: #e53e3e; font-size: 1.5rem; margin-top: 0; }\n";
$compiled .= "        p { line-height: 1.6; margin-bottom: 1.5rem; }\n";
$compiled .= "        code { background-color: #edf2f7; padding: 0.2rem 0.4rem; border-radius: 4px; font-family: monospace; font-size: 0.9em; }\n";
$compiled .= "    </style>\n";
$compiled .= "</head>\n";
$compiled .= "<body>\n";
$compiled .= "    <div class='card'>\n";
$compiled .= "        <h1>PHP Version Error</h1>\n";
$compiled .= "        <p><strong>IndieInABox</strong> requires PHP version <strong>8.2.0</strong> or higher.</p>\n";
$compiled .= "        <p>Your current PHP version is <code>\" . htmlspecialchars(PHP_VERSION) . \"</code> which is too old. Please upgrade PHP to run this application.</p>\n";
$compiled .= "    </div>\n";
$compiled .= "</body>\n";
$compiled .= "</html>\";\n";
$compiled .= "            exit(1);\n";
$compiled .= "        }\n";
$compiled .= "    }\n";
$compiled .= "}\n\n";

$globalFiles = [];

// Output each class file in its own namespace block to prevent use-statement import conflicts
foreach ($appFiles as $file) {
    $content = file_get_contents($file);
    
    // Determine namespace
    $namespace = '';
    if (preg_match('/^\s*namespace\s+([^;{\s]+)\s*;/m', $content, $matches)) {
        $namespace = $matches[1];
    }
    
    // Clean content
    // Remove opening <?php
    $cleaned = preg_replace('/^<\?php\s*/', '', $content);
    // Remove strict types
    $cleaned = preg_replace('/declare\(\s*strict_types\s*=\s*1\s*\);/', '', $cleaned);
    // Remove namespace statement
    $cleaned = preg_replace('/^\s*namespace\s+[^;{\s]+\s*;/m', '', $cleaned);
    
    $cleaned = trim($cleaned);
    $relativeName = str_replace($base . '/', '', $file);
    
    if ($namespace !== '') {
        $compiled .= "namespace {$namespace} {\n";
        $compiled .= "    // File: {$relativeName}\n";
        $lines = explode("\n", $cleaned);
        foreach ($lines as $line) {
            $compiled .= "    " . $line . "\n";
        }
        $compiled .= "}\n\n";
    } else {
        $globalFiles[] = [
            'file' => $relativeName,
            'content' => $cleaned
        ];
    }
}

// Output global function/library files in their own separate global namespace blocks
foreach ($globalFiles as $info) {
    $compiled .= "namespace {\n";
    $compiled .= "    // File: {$info['file']}\n";
    $lines = explode("\n", $info['content']);
    foreach ($lines as $line) {
        $compiled .= "    " . $line . "\n";
    }
    $compiled .= "}\n\n";
}

// Append build.php logic (runner) wrapped in its own global block
$buildContent = file_get_contents($base . '/build.php');
// Strip <?php and strict types
$buildContent = preg_replace('/^<\?php\s*/', '', $buildContent);
$buildContent = preg_replace('/declare\(\s*strict_types\s*=\s*1\s*\);/', '', $buildContent);
// Strip require_once bootstrap/app.php
$buildContent = preg_replace('/require_once\s+__DIR__\s*\.\s*[\'"]\/bootstrap\/app\.php[\'"]\s*;/', '', $buildContent);

// Extract all "use" statements from the runner code
$useStatements = [];
if (preg_match_all('/^\s*use\s+[^;]+;/m', $buildContent, $useMatches)) {
    $useStatements = $useMatches[0];
    $buildContent = preg_replace('/^\s*use\s+[^;]+;/m', '', $buildContent);
}

$compiled .= "namespace {\n";

// Insert "use" statements at the top of the global namespace runner block
if (!empty($useStatements)) {
    foreach ($useStatements as $useStmt) {
        $compiled .= "    " . trim($useStmt) . "\n";
    }
    $compiled .= "\n";
}

    $runnerCode = "    \$base = __DIR__;\n";

// Inject SQL schema as a constant or variable string
$sqlContent = file_get_contents($base . '/database.sql');
$runnerCode .= "    \$__SQL_SCHEMA__ = <<< 'SQL'\n";
$runnerCode .= $sqlContent . "\n";
$runnerCode .= "SQL;\n\n";

// Inject embedded default theme
function getThemeFiles(string $dir): array {
    if (!is_dir($dir)) return [];
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getBasename() !== '.gitkeep' && $file->getBasename() !== '.gitignore') {
            $files[$file->getPathname()] = file_get_contents($file->getPathname());
        }
    }
    return $files;
}

$themeViews = getThemeFiles($base . '/resources/views');
$themeStatic = getThemeFiles($base . '/resources/static');

$runnerCode .= "class DefaultTheme {\n";
$runnerCode .= "    private static \$views = [\n";
foreach ($themeViews as $path => $content) {
    $rel = str_replace($base . '/resources/', '', $path);
    $runnerCode .= "        '" . addslashes(str_replace('\\', '/', $rel)) . "' => '" . base64_encode($content) . "',\n";
}
$runnerCode .= "    ];\n";
$runnerCode .= "    private static \$staticFiles = [\n";
foreach ($themeStatic as $path => $content) {
    $rel = str_replace($base . '/resources/', '', $path);
    $runnerCode .= "        '" . addslashes(str_replace('\\', '/', $rel)) . "' => '" . base64_encode($content) . "',\n";
}
$runnerCode .= "    ];\n";
$runnerCode .= "    public static function getView(string \$path): ?string {
        return isset(self::\$views[\$path]) ? base64_decode(self::\$views[\$path]) : null;
    }
    public static function getViews(): array {
        \$files = [];
        foreach (self::\$views as \$path => \$b64) {
            \$files[\$path] = base64_decode(\$b64);
        }
        return \$files;
    }\n";
$runnerCode .= "    public static function getStaticFiles(): array {\n";
$runnerCode .= "        \$files = [];\n";
$runnerCode .= "        foreach (self::\$staticFiles as \$path => \$b64) {\n";
$runnerCode .= "            \$files[\$path] = base64_decode(\$b64);\n";
$runnerCode .= "        }\n";
$runnerCode .= "        return \$files;\n";
$runnerCode .= "    }\n";
$runnerCode .= "}\n\n";

// Inject install.php logic adapted for the single file
$runnerCode .= <<<'EOT'
    $configFile = $base . DIRECTORY_SEPARATOR . '.config.php';
    if (!file_exists($configFile)) {
        if (PHP_SAPI === 'cli') {
            file_put_contents('php://stderr', "\033[31;1mError: Database is not configured. Please run the web installer first.\033[0m\n");
            exit(1);
        } else {
            if (!extension_loaded('pdo_sqlite')) {
                die("<h1>Error: PDO_SQLite extension is not enabled in PHP. Please enable it to continue.</h1>");
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['data_dir'])) {
                $dataDir = rtrim($_POST['data_dir'], '/\\');
                
                if (!is_dir($dataDir)) {
                    @mkdir($dataDir, 0755, true);
                }
                
                if (!is_writable($dataDir)) {
                    $error = "The directory '$dataDir' is not writable or could not be created.";
                } else {
                    @mkdir($dataDir . DIRECTORY_SEPARATOR . 'microsub', 0755, true);
                    @mkdir($dataDir . DIRECTORY_SEPARATOR . 'activitypub', 0755, true);

                    $dbPath = $dataDir . DIRECTORY_SEPARATOR . '.indieinabox.sqlite';
                    $configContent = "<?php\n\nreturn [\n    'data_dir' => '" . str_replace("'", "\\'", $dataDir) . "',\n    'db_path' => '" . str_replace("'", "\\'", $dbPath) . "'\n];\n";
                    
                    if (file_put_contents($configFile, $configContent) !== false) {
                        try {
                            $db = new \PDO('sqlite:' . $dbPath);
                            $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                            $db->exec($__SQL_SCHEMA__);
                            
                            $title = $_POST['title'] ?? 'My Site';
                            $sitename = $_POST['sitename'] ?? 'My Site Name';
                            $fqdn = rtrim($_POST['fqdn'] ?? '', '/');
                            $password = $_POST['password'] ?? '';
                            
                            $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value");
                            $stmt->execute(['title', $title]);
                            $stmt->execute(['sitename', $sitename]);
                            if (!empty($fqdn)) {
                                $stmt->execute(['fqdn', $fqdn]);
                            }
                            if (!empty($password)) {
                                $stmt->execute(['indieauth_password', password_hash($password, PASSWORD_BCRYPT)]);
                            }
                        } catch (\Exception $e) {
                            die("Database creation failed: " . $e->getMessage());
                        }
                        
                        $contentDir = $base . DIRECTORY_SEPARATOR . 'content';
                        $articlesDir = $contentDir . DIRECTORY_SEPARATOR . 'articles';
                        $notesDir = $contentDir . DIRECTORY_SEPARATOR . 'notes';
                        
                        if (!is_dir($articlesDir)) {
                            @mkdir($articlesDir, 0755, true);
                            $welcomeArticle = "---\ntitle: Welcome to indieinabox\ndate: " . date('Y-m-d H:i:s') . "\n---\n\nWelcome to your new Indieinabox site!\n\nIndieinabox is a lightweight, static-site generator and IndieWeb-compatible server built for individuals who want to own their content. It integrates with the Fediverse and Microsub, allowing you to read, write, and interact with the decentralized web without giving up control of your data.\n\nTo learn more about how to configure your site, change themes, or connect to the Fediverse, please check out the [official documentation](https://github.com/indieinabox/indieinabox).\n";
                            file_put_contents($articlesDir . DIRECTORY_SEPARATOR . 'welcome-to-indieinabox.md', $welcomeArticle);
                        }
                        
                        if (!is_dir($notesDir)) {
                            @mkdir($notesDir, 0755, true);
                            $welcomeNote = "---\ndate: " . date('Y-m-d H:i:s') . "\n---\n\nThere is immense power in having total control over your own data. By hosting your own site, you decide what stays, what goes, and who gets to see it. Welcome to the IndieWeb.\n";
                            file_put_contents($notesDir . DIRECTORY_SEPARATOR . 'data-ownership.md', $welcomeNote);
                        }
                        
                        if (class_exists('\Indieinabox\SiteBuilder')) {
                            \Indieinabox\Database::$dataDir = $dataDir;
                            \Indieinabox\Database::connect($dbPath);
                            $site = new \Indieinabox\Site();
                            $site->paths->baseDir = $base;
                            $site->config = \Indieinabox\Database::getAllSettings();
                            
                            $baseOut = $site->config['outputdir'] ?? 'public';
                            $site->paths->outputDirHtml = $baseOut . '_html';
                            $site->paths->outputDirGemini = $baseOut . '_gemini';
                            $site->paths->outputDirGopher = $baseOut . '_gopher';
                            $site->paths->outputDirMedia = $baseOut . '_media';
                            $site->paths->contentDir = 'content';
                            
                            if (isset($site->config['active_theme']) && $site->config['active_theme'] !== 'default') {
                                $site->paths->themeDir = $dataDir . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $site->config['active_theme'];
                            } else {
                                $site->paths->themeDir = $base . DIRECTORY_SEPARATOR . 'resources'; // compiled bundle defaults to embedded if not found later
                            }
                            
                            $builder = new \Indieinabox\SiteBuilder($site);
                            $builder->build();
                        }
                        
                        header("Location: /admin/config");
                        exit;
                    } else {
                        $error = "Failed to write .config.php file. Check permissions on root folder.";
                    }
                }
            }

            $parentDir = dirname($base);
            $defaultDataDir = $parentDir . DIRECTORY_SEPARATOR . 'indieinabox-data';
            if (!is_writable($parentDir)) {
                $defaultDataDir = $base . DIRECTORY_SEPARATOR . 'indieinabox-data';
            }
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $defaultFqdn = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8081');

            echo <<< 'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>IndieInABox Installation</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #f4f4f5; color: #333; padding: 2rem; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; color: #2563eb; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; font-weight: bold; margin-bottom: 0.5rem; }
        input[type="text"], input[type="password"], input[type="url"] { width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background: #2563eb; color: #fff; border: none; padding: 0.75rem 1.5rem; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #1d4ed8; }
        .error { color: #dc2626; background: #fef2f2; border: 1px solid #f87171; padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to IndieInABox</h1>
        <p>It looks like this is your first time running the application or the database configuration is missing.</p>
HTML;
            if (isset($error)) {
                echo '<div class="error">' . htmlspecialchars($error) . '</div>';
            }
            echo <<< 'HTML'
        <form method="POST">
            <div class="form-group">
                <label for="data_dir">Data Directory Absolute Path</label>
                <input type="text" id="data_dir" name="data_dir" value="
HTML;
            echo htmlspecialchars($defaultDataDir);
            echo <<< 'HTML'
" required>
                <small style="color: #666; display: block; margin-top: 0.5rem;">
                    This directory will contain the SQLite database and all inbox files. Ensure it is writable by the PHP process.
                </small>
            </div>
            <div class="form-group">
                <label for="title">Site Title</label>
                <input type="text" id="title" name="title" value="My Site" required>
            </div>
            <div class="form-group">
                <label for="sitename">Site Name (Short)</label>
                <input type="text" id="sitename" name="sitename" value="My Site Name" required>
            </div>
            <div class="form-group">
                <label for="fqdn">Site URL (FQDN)</label>
                <input type="url" id="fqdn" name="fqdn" value="
HTML;
            echo htmlspecialchars($defaultFqdn);
            echo <<< 'HTML'
" required>
            </div>
            <div class="form-group">
                <label for="password">Admin Password</label>
                <input type="password" id="password" name="password" required>
                <small style="color: #666; display: block; margin-top: 0.5rem;">
                    This password will be used to log into your site and via IndieAuth.
                </small>
            </div>
            <button type="submit">Install & Build Site</button>
        </form>
    </div>
</body>
</html>
HTML;
            exit;
        }
    }

    $dbConfig = require $configFile;
    if (!isset($dbConfig['data_dir'])) {
        if (isset($dbConfig['db_path'])) {
            $dbConfig['data_dir'] = dirname($dbConfig['db_path']);
        } else {
            die("Error: Invalid .config.php format. Missing 'data_dir'.");
        }
    }

    try {
        $dbPath = $dbConfig['data_dir'] . '/.indieinabox.sqlite';
        if (isset($dbConfig['db_path']) && file_exists($dbConfig['db_path'])) {
            $dbPath = $dbConfig['db_path'];
        }
        \Indieinabox\Database::$dataDir = $dbConfig['data_dir'];
        \Indieinabox\Database::connect($dbPath);
    } catch (\Exception $e) {
        die("Database Connection Error: " . $e->getMessage());
    }

EOT;

$runnerCode .= $buildContent;

$compiled .= "    // Global Runner Execution\n";
$compiled .= "    \$isCliRunner = (isset(\$_SERVER['SCRIPT_FILENAME']) && realpath(\$_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__));\n";
$compiled .= "    if (\$isCliRunner) {\n";

$lines = explode("\n", trim($runnerCode));
foreach ($lines as $line) {
    $compiled .= "        " . $line . "\n";
}

$compiled .= "    }\n";
$compiled .= "}\n";

file_put_contents($base . '/indieinabox.php', $compiled);
echo "Application successfully compiled into single-file: indieinabox.php\n";
