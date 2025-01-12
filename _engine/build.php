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

// $config = Yaml::parseFile($base . DS . "config.yml");
$yaml = new \Alchemy\Component\Yaml\Yaml();
$config = $yaml->loadFile($base . DS . "config.yml");
var_dump($config);
if (isset($options["d"])) {
    $config["dev"] = true;
}
if (isset($options["s"])) {
    $config["skipstatic"] = true;
}
if (isset($config["base"])) {
    $config["base"] = trim($config["base"], "/");
    if (strlen($config["base"]) > 0) {
        $config["base"] = "/" . $config["base"];
    }
} else {
    $config["base"] = "";
}
if (isset($options["f"])) {
    $config["forcestaticoverride"] = true;
}

if (!isset($config["lang"])) {
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
echo "Assests are at " . ASSETS . "\n";

$site = new Site();
$site->basedir = $base;
$configs = [
    "title",
    "sitename",
    "author",
    "support",
    "buildall",
    "outputdir",
    "contentdir",
    "defaultcategory",
    "lang",
    "defaultlang",
    "fqdn",
    "htmlpostprocessing",
    "dev",
    "skipstatic"
];
foreach ($configs as $c) {
    $site->$c = $config[$c] ?? $site->$c;
}

$pages = [];
$p = [];

/* Main functions */

scan($base . DS . "_content");
generateHTMLFiles($pages);
generateFeed();
copyAssets($base . DS . "_template");
if ($site->skipstatic) {
    echo "Skipping static files\n";
} else {
    copyStatic($base . DS . "_static");
}
foreach ($site->types as $type => $value) {
    echo $type . PHP_EOL;
}
echo "Build complete\n";
