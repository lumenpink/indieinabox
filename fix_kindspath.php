<?php
require 'vendor/autoload.php';
require 'app/Database.php';

$dbPath = '/home/lumen/jardim/data/.indieinabox.sqlite';
\Indieinabox\Core\Database::connect($dbPath);
$db = \Indieinabox\Core\Database::getDb();

$kinds = \Indieinabox\Core\Database::getKinds();
$kindspath = [];

foreach ($kinds as $k => $conf) {
    $cd = $conf['content_dir'] ?? [];
    if (is_array($cd)) {
        $kindspath[$k] = array_values($cd);
    } else {
        $kindspath[$k] = [$cd];
    }
}

\Indieinabox\Core\Database::saveSetting('kindspath', json_encode($kindspath, JSON_UNESCAPED_UNICODE));
echo "Fixed kindspath.\n";
