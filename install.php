<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    die("<h1>Error: PDO_SQLite extension is not enabled in PHP. Please enable it to continue.</h1>");
}

// $baseDir is the directory where the user executed the script (filesystem)
$baseDir = dirname(realpath($_SERVER['SCRIPT_FILENAME']));
$configFile = $baseDir . DIRECTORY_SEPARATOR . '.config.php';

// $bundleDir is where the script source lives (could be inside a phar)
$bundleDir = __DIR__;
$schemaFile = $bundleDir . DIRECTORY_SEPARATOR . 'database.sql';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['data_dir'])) {
    $dataDir = rtrim($_POST['data_dir'], '/\\');
    
    // Create the directory if it doesn't exist
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }
    
    if (!is_writable($dataDir)) {
        $error = "The directory '$dataDir' is not writable or could not be created.";
    } else {
        @mkdir($dataDir . DIRECTORY_SEPARATOR . 'microsub', 0755, true);
        @mkdir($dataDir . DIRECTORY_SEPARATOR . 'activitypub', 0755, true);

        // Save .config.php
        $dbPath = $dataDir . DIRECTORY_SEPARATOR . '.indieinabox.sqlite';
        $configContent = "<?php\n\nreturn [\n    'data_dir' => '" . str_replace("'", "\\'", $dataDir) . "',\n    'db_path' => '" . str_replace("'", "\\'", $dbPath) . "'\n];\n";
        
        if (file_put_contents($configFile, $configContent) !== false) {
            
            // Run schema if exists
            if (file_exists($schemaFile)) {
                try {
                    $db = new \PDO('sqlite:' . $dbPath);
                    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $sql = file_get_contents($schemaFile);
                    $db->exec($sql);
                    
                    // Save the user's initial settings
                    $sitename = $_POST['sitename'] ?? 'My Site Name';
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $detectedFqdn = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8081');
                    $fqdn = rtrim($_POST['fqdn'] ?? $detectedFqdn, '/');
                    $password = $_POST['password'] ?? '';
                    
                    $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value");
                    $stmt->execute(['sitename', $sitename]);
                    if (!empty($fqdn)) {
                        $stmt->execute(['fqdn', $fqdn]);
                    }
                    if (!empty($password)) {
                        $stmt->execute(['indieauth_password', password_hash($password, PASSWORD_BCRYPT)]);
                    }
                    
                } catch (Exception $e) {
                    die("Database creation failed: " . $e->getMessage());
                }
            }
            
            // Generate default production content
            $contentDir = $baseDir . DIRECTORY_SEPARATOR . 'content';
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
            
            // Trigger site build to process HTML/Gem/Gopher immediately
            if (class_exists('\Indieinabox\SiteBuilder')) {
                \Indieinabox\Database::$dataDir = $dataDir;
                \Indieinabox\Database::connect($dbPath);
                $site = new \Indieinabox\Site();
                $site->paths->baseDir = $baseDir;
                $site->config = \Indieinabox\Database::getAllSettings();
                
                // We need to set the paths correctly before building
                $baseOut = $site->config['outputdir'] ?? 'public';
                $site->paths->outputDirHtml = $baseOut . '_html';
                $site->paths->outputDirGemini = $baseOut . '_gemini';
                $site->paths->outputDirGopher = $baseOut . '_gopher';
                $site->paths->outputDirMedia = $baseOut . '_media';
                $site->paths->contentDir = 'content';
                
                // Fallback theme path
                if (isset($site->config['active_theme']) && $site->config['active_theme'] !== 'default') {
                    $site->paths->themeDir = $dataDir . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $site->config['active_theme'];
                } else {
                    $site->paths->themeDir = $bundleDir . DIRECTORY_SEPARATOR . 'resources';
                }
                
                $builder = new \Indieinabox\SiteBuilder($site);
                $builder->build();
            }
            
            header("Location: /admin/microsub");
            exit;
        } else {
            $error = "Failed to write .config.php file. Check permissions on root folder.";
        }
    }
}

// Default Data directory suggestion
// We try to suggest one level up (../data) for security, falling back to ./data
$parentDir = dirname($baseDir);
$defaultDataDir = $parentDir . DIRECTORY_SEPARATOR . 'indieinabox-data';
if (!is_writable($parentDir)) {
    $defaultDataDir = $baseDir . DIRECTORY_SEPARATOR . 'indieinabox-data';
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$defaultFqdn = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8081');

?>
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
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="data_dir">Data Directory Absolute Path</label>
                <input type="text" id="data_dir" name="data_dir" value="<?php echo htmlspecialchars($defaultDataDir); ?>" required>
                <small style="color: #666; display: block; margin-top: 0.5rem;">
                    This directory will contain the SQLite database and all inbox files. Ensure it is writable by the PHP process.
                </small>
            </div>
            <div class="form-group">
            </div>
            <div class="form-group">
                <label for="sitename">Site Name (Short)</label>
                <input type="text" id="sitename" name="sitename" value="My Site Name" required>
            </div>
            <div class="form-group">
                <label for="fqdn">Site URL (FQDN)</label>
                <input type="url" id="fqdn" name="fqdn" value="<?php echo htmlspecialchars($defaultFqdn); ?>" required>
                <small style="color: #666; display: block; margin-top: 0.5rem;">
                    Automatically detected. Change this only if you are configuring the site through a proxy with a different domain.
                </small>
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
