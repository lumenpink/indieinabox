<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/Yaml.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/HttpSignature.php';
require_once __DIR__ . '/app/Whostyles.php';
require_once __DIR__ . '/app/Site.php';
require_once __DIR__ . '/app/BackgroundWorker.php';
require_once __DIR__ . '/app/Updater.php';

use Indieinabox\Site;
use Indieinabox\Database;
use Indieinabox\BackgroundWorker;
use Indieinabox\Updater;

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

echo "Checking for updates...\n";
$lastCheck = (int)Database::getSetting('last_update_check', 0);
// Check updates every 6 hours
if (time() - $lastCheck > 21600) {
    Updater::checkAvailableVersions();
    
    // Auto-upgrade logic
    $autoUpgradeNightly = (bool)Database::getSetting('auto_upgrade_nightly', false);
    $autoUpgradeStable = (bool)Database::getSetting('auto_upgrade_stable', false);
    
    if ($autoUpgradeNightly || $autoUpgradeStable) {
        $available = Database::getSetting('available_updates', []);
        if (!empty($available)) {
            // Pick the latest according to preferences
            $target = null;
            foreach ($available as $release) {
                if ($release['prerelease'] && $autoUpgradeNightly) {
                    $target = $release;
                    break;
                }
                if (!$release['prerelease'] && $autoUpgradeStable) {
                    $target = $release;
                    break;
                }
            }
            
            if ($target) {
                $lastInstalled = Database::getSetting('last_installed_update_id', null);
                if ($target['id'] !== $lastInstalled) {
                    echo "Auto-upgrading to " . $target['name'] . "...\n";
                    if (Updater::downloadAndInstall($target['download_url'])) {
                        Database::saveSetting('last_installed_update_id', $target['id']);
                        echo "Auto-upgrade successful.\n";
                    } else {
                        echo "Auto-upgrade failed.\n";
                    }
                }
            }
        }
    }
} else {
    echo "Skipping update check (last check was recent).\n";
}
