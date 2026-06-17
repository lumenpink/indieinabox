<?php

declare(strict_types=1);

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

use Indieinabox\Parsedown;
use Indieinabox\Yaml;
use Indieinabox\Site;
use Indieinabox\Pages;

require_once __DIR__ . '/bootstrap/app.php';

$options = [];
if (php_sapi_name() === 'cli') {
    $options = getopt("sdf"); // Get the options passed to the script
}
// -s - skip the static copy
// -d - enable dev mode (include live-reload script)
// -f - force static override

$base = __DIR__;

mb_internal_encoding("UTF-8");

$parsedown = new Parsedown();

$yaml = new Yaml();
/** @var array{
 *     base: string,
 *     title?: string,
 *     sitename?: string,
 *     support?: string[],
 *     buildall?: bool,
 *     outputdir?: string,
 *     contentdir?: string,
 *     defaultcategory?: string,
 *     lang?: string|string[],
 *     defaultlang?: string,
 *     fqdn?: string,
 *     author?: string,
 *     htmlpostprocessing?: string,
 *     dev?: bool,
 *     skipstatic?: bool,
 *     forcestaticoverride?: bool
 * } $config
 */
$configFile = $base . DIRECTORY_SEPARATOR . "config.yml";
if (file_exists($base . DIRECTORY_SEPARATOR . ".config.yml")) {
    $configFile = $base . DIRECTORY_SEPARATOR . ".config.yml";
}
$config = $yaml->loadFile($configFile);
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

if (php_sapi_name() === 'cli') {
    echo "Building at " . $config["base"] . "\n";
    echo "Assets are at " . ASSETS . "\n";
}

$site = new Site();
$site->paths->baseDir = $base;
if (isset($config['title'])) {
    $site->metadata->title = $config['title'];
}
if (isset($config['sitename'])) {
    $site->metadata->sitename = $config['sitename'];
}
if (isset($config['author'])) {
    $site->metadata->author = $config['author'];
}
if (isset($config['fqdn'])) {
    $site->metadata->fqdn = $config['fqdn'];
}
if (isset($config['indieauth_password'])) {
    $site->metadata->indieauthPassword = (string)$config['indieauth_password'];
}
if (isset($config['support'])) {
    $site->support->support = $config['support'];
}
if (isset($config['buildall'])) {
    $site->options->buildAll = $config['buildall'];
}
if (isset($config['outputdir'])) {
    $site->paths->outputDir = $config['outputdir'];
}
if (isset($config['contentdir'])) {
    $site->paths->contentDir = $config['contentdir'];
}
if (isset($config['defaultcategory'])) {
    $site->support->defaultCategory = $config['defaultcategory'];
}
$site->localization->lang = $config['lang'];
$site->localization->defaultLang = $config['defaultlang'];
if (isset($config['htmlpostprocessing'])) {
    $site->options->htmlpostprocessing = $config['htmlpostprocessing'];
}
if (isset($config['dev'])) {
    $site->options->dev = $config['dev'];
}
if (isset($config['skipstatic'])) {
    $site->options->skipStatic = $config['skipstatic'];
}
if (isset($config['forcestaticoverride'])) {
    $site->options->forceStaticOverride = $config['forcestaticoverride'];
}

if (php_sapi_name() === 'cli') {
    $builder = new \Indieinabox\SiteBuilder($site);
    $builder->build();
    $pages = $builder->getPages();
    echo "Build complete\n";
} else {
    $router = new \Indieinabox\WebRouter($site);
    $router->handleRequest();
}
