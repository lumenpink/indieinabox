<?php
$files = [
    'app/Database.php',
    'app/IndieAuthHandler.php',
    'app/ConfigHandler.php',
    'app/Helper.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // In Database.php
    $content = str_replace(
        'throw new Exception("\PDO extension is not loaded.");',
        'throw new Exception("PDO extension is not loaded.");',
        $content
    );
    $content = str_replace(
        "if (!extension_loaded('sqlite3')) {",
        "if (!extension_loaded('pdo_sqlite')) {",
        $content
    );
    $content = str_replace(
        "self::\$db = new \PDO(\$path, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);",
        "self::\$db = new \PDO('sqlite:' . \$path, '', '', [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);",
        $content
    );
    $content = str_replace('self::$db->busyTimeout(5000);', 'self::$db->setAttribute(\PDO::ATTR_TIMEOUT, 5);', $content);

    // Replace SQLITE3 constants
    $content = str_replace('SQLITE3_TEXT', '\PDO::PARAM_STR', $content);
    $content = str_replace('\SQLITE3_TEXT', '\PDO::PARAM_STR', $content);
    $content = str_replace('SQLITE3_INTEGER', '\PDO::PARAM_INT', $content);
    $content = str_replace('\SQLITE3_INTEGER', '\PDO::PARAM_INT', $content);
    $content = str_replace('SQLITE3_ASSOC', '\PDO::FETCH_ASSOC', $content);
    $content = str_replace('\SQLITE3_ASSOC', '\PDO::FETCH_ASSOC', $content);

    // Replace $result = $db->query(...) with $result = $db->query(...) [PDO returns PDOStatement which can be fetched]
    $content = str_replace('fetchArray', 'fetch', $content);
    
    // Replace $result = $stmt->execute(); fetchArray() pattern
    // In SQLite3, execute() returns SQLite3Result. In PDO it returns bool, and you fetch from $stmt.
    // For IndieAuthHandler: $codeData = $result ? $result->fetchArray(\SQLITE3_ASSOC) : false;
    // We can replace `$result = $stmt->execute();\n        $codeData = $result ? $result->fetch(\PDO::FETCH_ASSOC) : false;`
    // with `$stmt->execute();\n        $codeData = $stmt->fetch(\PDO::FETCH_ASSOC);`
    
    $content = preg_replace(
        '/\$result = \$stmt->execute\(\);\s*\$(\w+) = \$result \? \$result->fetch\(\\\\PDO::FETCH_ASSOC\) : false;/',
        '$stmt->execute();' . "\n" . '        $$1 = $stmt->fetch(\PDO::FETCH_ASSOC);',
        $content
    );
    $content = preg_replace(
        '/\$result = \$stmt->execute\(\);\s*\$(\w+) = \$result->fetch\(\\\\?PDO::FETCH_ASSOC\);/',
        '$stmt->execute();' . "\n" . '        $$1 = $stmt->fetch(\PDO::FETCH_ASSOC);',
        $content
    );

    $content = preg_replace(
        '/\$res = \$stmt->execute\(\);\s*\$(\w+) = \$res \? \$res->fetch\(\\\\?PDO::FETCH_ASSOC\) : false;/',
        '$stmt->execute();' . "\n" . '                        $$1 = $stmt->fetch(\PDO::FETCH_ASSOC);',
        $content
    );

    // Replace remaining $result = $stmt->execute() where $result is not used
    // Actually, let's just make sure we catch any while ($row = $result->fetch()) where it was a prepared statement.
    // Wait, if it's $result = $db->query(...), PDO query returns PDOStatement so while($row = $result->fetch()) WORKS!
    // But if it's $result = $stmt->execute(), PDO execute returns bool, so we must fetch from $stmt!
    
    // In Database.php getSetting:
    // $result = $stmt->execute();
    // if ($result) {
    //     $row = $result->fetch(\PDO::FETCH_ASSOC);
    $content = str_replace(
        '$result = $stmt->execute();
            if ($result) {
                $row = $result->fetch(\PDO::FETCH_ASSOC);',
        '$stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {',
        $content
    );

    // In IndieAuthHandler.php
    $content = str_replace(
        '$result = $stmt->execute();
        $codeData = $result ? $result->fetch(\PDO::FETCH_ASSOC) : false;',
        '$stmt->execute();
        $codeData = $stmt->fetch(\PDO::FETCH_ASSOC);',
        $content
    );

    $content = str_replace(
        '$result = $stmt->execute();
        $tokenData = $result ? $result->fetch(\PDO::FETCH_ASSOC) : false;',
        '$stmt->execute();
        $tokenData = $stmt->fetch(\PDO::FETCH_ASSOC);',
        $content
    );
    
    // Fix lastInsertId
    $content = str_replace('lastInsertRowID()', 'lastInsertId()', $content);

    // Type hints
    $content = str_replace('?SQLite3', '?\PDO', $content);
    $content = str_replace(' SQLite3', ' \PDO', $content);
    $content = str_replace(': SQLite3', ': \PDO', $content);

    file_put_contents($file, $content);
    echo "Processed $file\n";
}
