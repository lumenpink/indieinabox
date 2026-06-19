<?php

echo "Watching for changes in resources, app, theme, and content...\n";

$directories = ['resources', 'app', 'content', 'theme'];
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
    
    if ($lastHash !== '' && $currentHash !== $lastHash) {
        echo "[" . date('H:i:s') . "] Changes detected! Rebuilding...\n";
        passthru('php build.php -d');
    }
    
    $lastHash = $currentHash;
    sleep(1);
}
