<?php
require 'vendor/autoload.php';
require 'app/Database.php';

$dbPath = '/home/lumen/jardim/data/.indieinabox.sqlite';
\Indieinabox\Database::connect($dbPath);
$db = \Indieinabox\Database::getDb();

$kinds = \Indieinabox\Database::getKinds();
$kindspath = [];

foreach ($kinds as $k => $conf) {
    $cd = $conf['content_dir'] ?? [];
    if (is_array($cd)) {
        $kindspath[$k] = array_values($cd);
    } else {
        $kindspath[$k] = [$cd];
    }
}

\Indieinabox\Database::saveSetting('kindspath', json_encode($kindspath, JSON_UNESCAPED_UNICODE));
echo "Fixed kindspath.\n";
