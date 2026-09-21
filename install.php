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
    $sitename = $_POST['sitename'] ?? 'My Site Name';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $detectedFqdn = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8081');
    $fqdn = rtrim($_POST['fqdn'] ?? $detectedFqdn, '/');
    $password = $_POST['password'] ?? '';

    try {
        $installer = new \Indieinabox\Services\InstallService();
        $installer->install([
            'base_dir' => $baseDir,
            'data_dir' => $dataDir,
            'sitename' => $sitename,
            'fqdn' => $fqdn,
            'password' => $password,
            'build' => true,
        ]);
        header("Location: /admin/microsub");
        exit;
    } catch (\Throwable $e) {
        $error = "Installation failed: " . $e->getMessage();
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
