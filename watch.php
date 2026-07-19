<?php

$config = require '.config.php';
$dbPath = $config['db_path'] ?? __DIR__ . '/data/indieinabox.sqlite';
$contentDir = 'content';
if (file_exists($dbPath)) {
    try {
        $db = new PDO('sqlite:' . $dbPath);
        $stmt = $db->query("SELECT value FROM settings WHERE key = 'contentdir'");
        if ($stmt) {
            $result = $stmt->fetchColumn();
            if ($result) {
                $contentDir = $result;
            }
        }
    } catch (PDOException $e) {
        // Ignore and fallback to 'content'
    }
}
$content_path = (str_starts_with($contentDir, DIRECTORY_SEPARATOR) || preg_match('#^[a-zA-Z]:\\\\#', $contentDir)) 
    ? rtrim($contentDir, DIRECTORY_SEPARATOR) 
    : __DIR__ . DIRECTORY_SEPARATOR . $contentDir;

echo "Starting PHP development server on localhost:8081...\n";
$server = proc_open('php -S localhost:8081 build.php', [STDIN, STDOUT, STDERR], $pipes);
if (!is_resource($server)) {
    die("Failed to start development server.\n");
}
register_shutdown_function(function() use ($server) {
    echo "Stopping development server...\n";
    proc_terminate($server);
});

echo "Watching for changes in resources, app, theme, and {$content_path}...\n";
$directories = ['resources', 'app', $content_path, 'theme'];
$lastHash = '';

while (true) {
    $currentHash = '';
    foreach ($directories as $dir) {
        if (!is_dir($dir)) continue;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($files as $file) {
            if ($file->isFile()) {
                $currentHash .= $file->getMTime();
            }
        }
    }
    
    $currentHash = md5($currentHash);
    
    if ($currentHash !== $lastHash) {
        echo "[" . date('H:i:s') . "] Changes detected! Rebuilding...\n";
        passthru('php compile.php');
        passthru('php build.php -d');
    }
    
    $lastHash = $currentHash;
    sleep(1);
}
