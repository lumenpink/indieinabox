<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$appDir = realpath(__DIR__ . '/../app');
$docsDir = realpath(__DIR__ . '/../docs') . '/api';

if (!is_dir($docsDir)) {
    mkdir($docsDir, 0755, true);
}

function getPhpFiles(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

function extractDocblock(?string $docComment): string
{
    if (!$docComment) {
        return '';
    }
    // Remove /**, */ and leading *
    $lines = explode("\n", $docComment);
    $output = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '/**' || $line === '*/') continue;
        if (str_starts_with($line, '* ')) {
            $output[] = substr($line, 2);
        } elseif ($line === '*') {
            $output[] = '';
        } else {
            $output[] = $line;
        }
    }
    return trim(implode("\n", $output));
}

function formatType(?ReflectionType $type): string
{
    if (!$type) {
        return 'mixed';
    }
    if ($type instanceof ReflectionUnionType) {
        $types = array_map(fn($t) => $t->getName(), $type->getTypes());
        return implode('|', $types);
    }
    if ($type instanceof ReflectionIntersectionType) {
        $types = array_map(fn($t) => $t->getName(), $type->getTypes());
        return implode('&', $types);
    }
    if ($type instanceof ReflectionNamedType) {
        $name = $type->getName();
        return $type->allowsNull() ? '?' . $name : $name;
    }
    return (string)$type;
}

$files = getPhpFiles($appDir);

echo "Generating API documentation...\n";

foreach ($files as $file) {
    // Convert path to namespace/class
    $relPath = substr($file, strlen($appDir) + 1);
    $relPath = str_replace('.php', '', $relPath);
    
    // Ignore function files or non-class files
    if (str_starts_with($relPath, 'functions/')) {
        continue;
    }
    
    $className = 'Indieinabox\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relPath);
    
    if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
        continue;
    }

    try {
        $reflector = new ReflectionClass($className);
    } catch (ReflectionException $e) {
        echo "Could not reflect $className: " . $e->getMessage() . "\n";
        continue;
    }

    $md = [];
    $md[] = "# " . $reflector->getShortName();
    $md[] = "**Namespace:** `" . $reflector->getNamespaceName() . "`";
    $md[] = "";
    
    $doc = extractDocblock($reflector->getDocComment() ?: null);
    if ($doc) {
        $md[] = $doc;
        $md[] = "";
    }
    
    // Properties (including private)
    $properties = $reflector->getProperties();
    if (count($properties) > 0) {
        $md[] = "## Properties";
        $md[] = "";
        foreach ($properties as $prop) {
            $modifiers = implode(' ', Reflection::getModifierNames($prop->getModifiers()));
            $type = formatType($prop->getType());
            $name = '$' . $prop->getName();
            
            $md[] = "### `$modifiers $type $name`";
            $pDoc = extractDocblock($prop->getDocComment() ?: null);
            if ($pDoc) {
                $md[] = "";
                $md[] = $pDoc;
            }
            $md[] = "";
        }
    }
    
    // Methods (including private)
    $methods = $reflector->getMethods();
    if (count($methods) > 0) {
        $md[] = "## Methods";
        $md[] = "";
        foreach ($methods as $method) {
            $modifiers = implode(' ', Reflection::getModifierNames($method->getModifiers()));
            $name = $method->getName();
            
            $params = [];
            foreach ($method->getParameters() as $param) {
                $pType = formatType($param->getType());
                $pName = '$' . $param->getName();
                $def = '';
                if ($param->isDefaultValueAvailable()) {
                    $val = $param->getDefaultValue();
                    if ($val === null) $def = ' = null';
                    elseif (is_string($val)) $def = " = '$val'";
                    elseif (is_bool($val)) $def = $val ? ' = true' : ' = false';
                    elseif (is_array($val)) $def = ' = []';
                    else $def = " = " . var_export($val, true);
                }
                $params[] = "$pType $pName$def";
            }
            
            $returnType = formatType($method->getReturnType());
            if ($returnType !== 'mixed' || $method->hasReturnType()) {
                $returnStr = ": $returnType";
            } else {
                $returnStr = "";
            }
            
            $sig = "`$modifiers function $name(" . implode(', ', $params) . ")$returnStr`";
            
            $md[] = "### $name()";
            $md[] = $sig;
            
            $mDoc = extractDocblock($method->getDocComment() ?: null);
            if ($mDoc) {
                $md[] = "";
                $md[] = $mDoc;
            }
            $md[] = "";
        }
    }
    
    // Write to docs/api
    $targetPath = $docsDir . DIRECTORY_SEPARATOR . str_replace(DIRECTORY_SEPARATOR, '-', $relPath) . '.md';
    file_put_contents($targetPath, implode("\n", $md));
}

echo "API documentation generated successfully in docs/api/.\n";

$isHtml = in_array('--html', $argv);

if ($isHtml) {
    echo "Generating HTML documentation...\n";
    $htmlDir = $docsDir . '/html';
    if (!is_dir($htmlDir)) {
        mkdir($htmlDir, 0755, true);
    }
    
    // We can use our own markdown parser
    $parser = new \Indieinabox\Markdown\ASTParser();
    $renderer = new \Indieinabox\Markdown\HtmlRenderer();
    
    // Get all generated MD files
    $mdFiles = glob($docsDir . '/*.md');
    $links = [];
    
    foreach ($mdFiles as $mdFile) {
        $basename = basename($mdFile, '.md');
        $links[] = "<li><a href=\"/docs/api/html/$basename.html\">$basename</a></li>";
    }
    
    $sidebar = "<ul>" . implode("\n", $links) . "</ul>";
    
    foreach ($mdFiles as $mdFile) {
        $basename = basename($mdFile, '.md');
        $mdContent = file_get_contents($mdFile);
        $ast = $parser->parse($mdContent);
        $htmlContent = $renderer->render($ast);
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$basename - Indieinabox API</title>
    <style>
        :root { --bg: #F4F1EA; --fg: #2C2E2F; --accent: #2C2E2F; }
        body { font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; margin: 0; display: flex; background: var(--bg); color: var(--fg); line-height: 1.6; }
        .sidebar { width: 300px; background: rgba(0,0,0,0.05); padding: 20px; overflow-y: auto; height: 100vh; position: fixed; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar li { margin-bottom: 5px; }
        .sidebar a { text-decoration: none; color: var(--accent); font-size: 0.9em; }
        .sidebar a:hover { text-decoration: underline; }
        .content { margin-left: 320px; padding: 40px; max-width: 800px; }
        pre { background: rgba(0, 0, 0, 0.05); padding: 1em; overflow-x: auto; }
        code { background: rgba(0, 0, 0, 0.05); padding: 2px 4px; font-size: 0.9em; }
        a { color: var(--accent); }
    </style>
</head>
<body>
    <div class="sidebar">
        <h3>API Reference</h3>
        $sidebar
    </div>
    <div class="content">
        $htmlContent
    </div>
</body>
</html>
HTML;
        file_put_contents($htmlDir . DIRECTORY_SEPARATOR . $basename . '.html', $html);
    }
    
    // Create an index file
    $indexHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indieinabox API</title>
    <style>
        :root { --bg: #F4F1EA; --fg: #2C2E2F; --accent: #2C2E2F; }
        body { font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; margin: 0; display: flex; background: var(--bg); color: var(--fg); line-height: 1.6; }
        .sidebar { width: 300px; background: rgba(0,0,0,0.05); padding: 20px; overflow-y: auto; height: 100vh; position: fixed; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar li { margin-bottom: 5px; }
        .sidebar a { text-decoration: none; color: var(--accent); font-size: 0.9em; }
        .sidebar a:hover { text-decoration: underline; }
        .content { margin-left: 320px; padding: 40px; max-width: 800px; }
        pre { background: rgba(0, 0, 0, 0.05); padding: 1em; overflow-x: auto; }
        code { background: rgba(0, 0, 0, 0.05); padding: 2px 4px; font-size: 0.9em; }
        a { color: var(--accent); }
    </style>
</head>
<body>
    <div class="sidebar">
        <h3>API Reference</h3>
        $sidebar
    </div>
    <div class="content">
        <h1>Indieinabox API Documentation</h1>
        <p>Welcome to the internal API documentation for Indieinabox.</p>
        <p>Select a class, interface, or trait from the sidebar to view its properties and methods.</p>
    </div>
</body>
</html>
HTML;
    file_put_contents($htmlDir . DIRECTORY_SEPARATOR . 'index.html', $indexHtml);
    
    echo "HTML documentation generated successfully in docs/api/html/.\n";
}
