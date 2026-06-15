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
