<?php
function loadEnv($path) {
    if (!file_exists($path)) return [];
    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0 || empty($line)) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim(trim($parts[1]), '"\'');
            $env[$key] = $val;
        }
    }
    return $env;
}
file_put_contents('.env.test', "APP_URL=https://test.cloudflare.com\n# Comment\nFQDN=http://local.test\n");
var_dump(loadEnv('.env.test'));
