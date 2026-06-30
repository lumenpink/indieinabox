<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    die("<h1>Error: PDO_SQLite extension is not enabled in PHP. Please enable it to continue.</h1>");
}

$baseDir = __DIR__;
$configFile = $baseDir . DIRECTORY_SEPARATOR . '.config.php';
$schemaFile = $baseDir . DIRECTORY_SEPARATOR . 'database.sql';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['data_dir'])) {
    $dataDir = rtrim($_POST['data_dir'], '/\\');
    
    // Create the directory if it doesn't exist
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }
    
    if (!is_writable($dataDir)) {
        $error = "The directory '$dataDir' is not writable or could not be created.";
    } else {
        // Create subdirectories for microsub/activitypub to avoid permission issues later
        @mkdir($dataDir . DIRECTORY_SEPARATOR . 'microsub', 0755, true);
        @mkdir($dataDir . DIRECTORY_SEPARATOR . 'activitypub', 0755, true);

        // Save .config.php
        $configContent = "<?php\n\nreturn [\n    'data_dir' => '" . str_replace("'", "\\'", $dataDir) . "'\n];\n";
        if (file_put_contents($configFile, $configContent) !== false) {
            
            // Run schema if exists
            if (file_exists($schemaFile)) {
                try {
                    $dbPath = $dataDir . DIRECTORY_SEPARATOR . 'indieinabox.sqlite';
                    $db = new \PDO('sqlite:' . $dbPath);
                    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $sql = file_get_contents($schemaFile);
                    $db->exec($sql);
                } catch (Exception $e) {
                    die("Database creation failed: " . $e->getMessage());
                }
            }
            
            header("Location: /");
            exit;
        } else {
            $error = "Failed to write .config.php file. Check permissions on root folder.";
        }
    }
}

// Default Data directory suggestion
// We try to suggest one level up (../data) for security, falling back to ./data
$parentDir = dirname($baseDir);
$defaultDataDir = $parentDir . DIRECTORY_SEPARATOR . 'data';
if (!is_writable($parentDir)) {
    $defaultDataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';
}

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
        input[type="text"] { width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
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
            <button type="submit">Install & Migrate Data</button>
        </form>
    </div>
</body>
</html>
