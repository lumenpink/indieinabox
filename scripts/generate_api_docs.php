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
