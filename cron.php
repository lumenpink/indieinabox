<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/bootstrap/app.php';

use Indieinabox\BackgroundWorker\BackgroundWorker;
use Indieinabox\Core\Database;
use Indieinabox\Site;

$config = require __DIR__ . '/.config.php';
Database::$dataDir = dirname($config['db_path']);
Database::connect($config['db_path']);

$site = new Site();
$site->paths->baseDir = __DIR__;
$site->config = Database::getAllSettings();
if (isset($site->config['fqdn'])) {
    $site->metadata->fqdn = $site->config['fqdn'];
}
if (isset($site->config['twtxt_following'])) {
    $site->twtxt->following = $site->config['twtxt_following'];
}
if (isset($site->config['twtxt_hubs'])) {
    $site->twtxt->hubs = $site->config['twtxt_hubs'];
}

$worker = new BackgroundWorker($site);

echo "Starting Background Worker...\n";
$worker->runAll();
echo "Background Worker completed.\n";

