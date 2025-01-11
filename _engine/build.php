<?php
foreach (glob(__DIR__ . "/functions/*.php") as $filename) {
    include  $filename;
}
foreach (glob(__DIR__ . "/../_data/*.php") as $filename) {
    include  $filename;
}
$options = getopt("sdf"); // Get the options passed to the script
# -s - skip the static copy
# -d - enable dev mode (include live-reload script)
# -f - force static override

$base = dirname(__DIR__);
define("DS", DIRECTORY_SEPARATOR);

use Symfony\Component\Yaml\Yaml;

mb_internal_encoding("UTF-8");

$parsedown = new Parsedown();

$site = Yaml::parseFile($base . DS . "config.yml");

$site["basedir"] = $base;

if (!isset($site["dev"])) {
    if (isset($options["d"])) {
        $site["dev"] = true;
    } else {
        $site["dev"] = false;
    }
}
if (isset($options["s"])) {
    $site["skipstatic"] = true;
} else {
    $site["skipstatic"] = false;
}
if (isset($site["base"])) {
    $site["base"] = trim($site["base"], "/");
    if (strlen($site["base"]) > 0) {
        $site["base"] = "/" . $site["base"];
    }
} else {
    $site["base"] = "";
}

if (isset($options["f"])) {
    $site["forcestaticoverride"] = true;
} else {
    $site["forcestaticoverride"] = false;
}

$supported_extensions = $site["support"];
$site["default-title"] = "Untitled";

if (!isset($site["output-dir"])) {
    $site["output-dir"] = "_site";
}

if (!isset($site["content-dir"])) {
    $site["content-dir"] = "_content";
}

if (!isset($site["date-format"])) {
    $site["date-format"] = "Y-m-d";
}

if (!isset($site["lang"])) {
    $site["default-lang"] = "en";
} else {
    if (is_array($site["lang"])) {
        $site["default-lang"] = $site["lang"][0];
    } else {
        $site["default-lang"] = $site["lang"];
    }
}
$site["copyright"] = $copyright ?: "ISC";

define("ASSETS", $site["base"] . "/assets");

echo "Building at " . $site["base"] . "\n";
echo "Assests are at " . ASSETS . "\n";

$pages = [];
$p = [];

/* Main functions */

scan($base . DS . "_content");
generateHTMLFiles($pages);
generateFeed();
copyAssets($base . DS . "_template");
if ($site["skipstatic"]) {
    echo "Skipping static files\n";
} else {
    copyStatic($base . DS . "_static");
}
echo "Build complete\n";
// var_dump($pages);
