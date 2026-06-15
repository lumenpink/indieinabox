<?php

declare(strict_types=1);

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}


use Indieinabox\Parsedown;
use Indieinabox\Yaml;
use Indieinabox\Site;
use Indieinabox\Pages;

/**
 * Indieinabox
 * All-one-social
 * php version 7-8
 *
 * @category Social
 * @package  Indieinabox
 * @author   Lumen Pink <hi@lumen.pink>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @version  GIT: 0.0.3
 * @link     https://Indieinabox.no.site.yet/
 */

// phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative
require_once __DIR__ . '/autoload.php'; //NOSONAR
foreach (glob(__DIR__ . "/functions/*.php") as $filename) {
    include_once  $filename; //NOSONAR
}
foreach (glob(__DIR__ . "/../_data/*.php") as $filename) {
    include_once  $filename; //NOSONAR
}


$options = getopt("sdf"); // Get the options passed to the script
// -s - skip the static copy
// -d - enable dev mode (include live-reload script)
// -f - force static override

$base = dirname(__DIR__);

mb_internal_encoding("UTF-8");

$parsedown = new Parsedown();

$yaml = new Yaml();
/** @var array{
 *     base: string,
 *     forcestaticoverride?: bool,
 *     lang?: string|string[],
 *     defaultlang?: string
 * } $config
 */
$config = $yaml->loadFile($base . DIRECTORY_SEPARATOR . "config.yml");
if (isset($options["d"])) {
    $config["dev"] = true;
}
if (isset($options["s"])) {
    $config["skipstatic"] = true;
}
$config["base"] = trim($config["base"], "/");
if (strlen($config["base"]) > 0) {
    $config["base"] = "/" . $config["base"];
}

if (isset($options["f"])) {
    $config["forcestaticoverride"] = true;
}

if (!isset($config["lang"])) {
    $config["lang"] = "en";
    $config["defaultlang"] = "en";
} else {
    if (is_array($config["lang"])) {
        $config["defaultlang"] = $config["lang"][0];
    } else {
        $config["defaultlang"] = $config["lang"];
    }
}


define("ASSETS", $config["base"] . "/assets");

echo "Building at " . $config["base"] . "\n";
echo "Assets are at " . ASSETS . "\n";

$site = new Site();
$site->paths->baseDir = $base;
$configs = [
    "title" => "metadata",
    "sitename" => "metadata",
    "author" => "metadata",
    "support" => "support",
    "buildall" => "options",
    "outputdir" => "paths",
    "contentdir" => "paths",
    "defaultcategory" => "support",
    "lang" => "localization",
    "defaultlang" => "localization",
    "fqdn" => "metadata",
    "htmlpostprocessing" => "options",
    "dev" => "options",
    "skipstatic" => "options",
    "forcestaticoverride" => "options"
];
foreach ($configs as $property => $prefix) {
    if (isset($config[$property])) {
        $mappedProperty = $property;
        if ($property === 'buildall') {
            $mappedProperty = 'buildAll';
        } elseif ($property === 'outputdir') {
            $mappedProperty = 'outputDir';
        } elseif ($property === 'contentdir') {
            $mappedProperty = 'contentDir';
        } elseif ($property === 'defaultcategory') {
            $mappedProperty = 'defaultCategory';
        } elseif ($property === 'defaultlang') {
            $mappedProperty = 'defaultLang';
        } elseif ($property === 'skipstatic') {
            $mappedProperty = 'skipStatic';
        } elseif ($property === 'forcestaticoverride') {
            $mappedProperty = 'forceStaticOverride';
        }
        $site->$prefix->$mappedProperty = $config[$property];
    }
}

$pages = new Pages();


/* Main functions */

recursive_rmdir($base . DIRECTORY_SEPARATOR . "_site");
scan($base . DIRECTORY_SEPARATOR . "_content");
generateHTMLFiles($pages);
generateFeed();
copyAssets($base . DIRECTORY_SEPARATOR . "_template");
if ($site->options->skipStatic) {
    echo "Skipping static files\n";
} else {
    copyStatic($base . DIRECTORY_SEPARATOR . "_static");
}
echo "Build complete\n";
