<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite')) {
    die("<h1>Error: PDO_SQLite extension is not enabled in PHP. Please enable it to continue.</h1>");
}

$baseDir = dirname(__DIR__);
$configFile = $baseDir . DIRECTORY_SEPARATOR . '.config.php';
$schemaFile = $baseDir . DIRECTORY_SEPARATOR . 'database.sql';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['db_path'])) {
    $dbPath = $_POST['db_path'];
    
    // Ensure the directory is writable
    $dir = dirname($dbPath);
    if (!is_writable($dir) && !is_writable($baseDir)) {
        $error = "The directory '$dir' is not writable.";
    } else {
        // Save .config.php
        $configContent = "<?php\n\nreturn [\n    'db_path' => '" . str_replace("'", "\\'", $dbPath) . "'\n];\n";
        if (file_put_contents($configFile, $configContent) !== false) {
            
            // Run schema if exists
            if (file_exists($schemaFile)) {
                try {
                    $db = new \PDO('sqlite:' . $dbPath);
                    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $sql = file_get_contents($schemaFile);
                    $db->exec($sql);
                    $db = null; // Close connection
                    
                    // Cleanup optional: unlink($schemaFile);
                    // Since it's development, we'll keep it or unlink it depending on preferences. Let's keep it.
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

// Default DB path suggestion
$defaultDbPath = $baseDir . DIRECTORY_SEPARATOR . 'indieinabox.sqlite';

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
                <label for="db_path">SQLite Database Absolute Path</label>
                <input type="text" id="db_path" name="db_path" value="<?php echo htmlspecialchars($defaultDbPath); ?>" required>
                <small style="color: #666; display: block; margin-top: 0.5rem;">
                    This file will be created if it doesn't exist. Ensure the directory is writable by the PHP process.
                </small>
            </div>
            <button type="submit">Install & Migrate Data</button>
        </form>
    </div>
</body>
</html>
