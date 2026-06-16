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

$runnerCode = "\n    // Compiled dynamic loading of composer autoloader and data arrays\n";
$runnerCode .= "    \$base = __DIR__;\n";
$runnerCode .= "    if (file_exists(\$base . '/vendor/autoload.php')) {\n";
$runnerCode .= "        include_once \$base . '/vendor/autoload.php';\n";
$runnerCode .= "    }\n";
$runnerCode .= "    foreach (glob(\$base . '/data/*.php') as \$filename) {\n";
$runnerCode .= "        include_once \$filename;\n";
$runnerCode .= "    }\n\n";
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
