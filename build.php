<?php

declare(strict_types=1);

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

use Indieinabox\Site\Site;
use Indieinabox\Page\Pages;

require_once __DIR__ . '/bootstrap/app.php';

$options = [];
if (php_sapi_name() === 'cli') {
    global $argv;
    $options = [];
    if (isset($argv)) {
        foreach ($argv as $arg) {
            if ($arg === '-s') $options['s'] = false;
            if ($arg === '-d') $options['d'] = false;
            if ($arg === '-f') $options['f'] = false;
            if ($arg === '-a') $options['a'] = false;
            if ($arg === '-M') $options['M'] = false;
            if ($arg === '-m') $options['m'] = false;
        }
    }
}
// -s - skip the static copy

// -f - force static override
// -a - force rebuild all pages (ignore partials)
// -M - skip media processing
// -m - only rebuild media (skip pages)

$base = __DIR__;

mb_internal_encoding("UTF-8");

// $yaml = new Yaml(); // Replaced with Database

$config = \Indieinabox\Core\Database::getAllSettings();
$config['kinds'] = \Indieinabox\Core\Database::getKinds();
$config['translations'] = \Indieinabox\Core\Database::getTranslations();
$config['urltranslations'] = \Indieinabox\Core\Database::getUrlTranslations();
if (empty($config['sitename'])) {
    // Default fallback if DB is somehow empty
    $config = [
        'base' => '/',
        'sitename' => 'My Site Name',
        'fqdn' => 'http://localhost:8080',
        'outputdir' => 'public',
        'contentdir' => 'content',
        'themedir' => 'resources',
        'lang' => 'en',
        'defaultlang' => 'en',
        'support' => ['md', 'txt', 'html', 'htm']
    ];
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
if (isset($options["a"])) {
    $config["forcerebuild"] = true;
}
if (isset($options["M"])) {
    $config["skipmedia"] = true;
}
if (isset($options["m"])) {
    $config["skippages"] = true;
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
    if (!isset($argv[1]) || str_starts_with($argv[1], '-')) {
        echo "Building at " . $config["base"] . "\n";
        echo "Assets are at " . ASSETS . "\n";
    }
}

$site = new Site();
\Indieinabox\Core\Container::getInstance()->instance(\Indieinabox\Site\Site::class, $site);
\Indieinabox\Core\Container::getInstance()->instance('site', $site);
$site->paths->baseDir = $base;
$site->config = $config;
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
    $site->options->buildAll = (bool)$config['buildall'];
}
if (isset($config['outputdir'])) {
    $baseOut = $config['outputdir'];
    $site->paths->outputDirHtml = $baseOut . '_html';
    $site->paths->outputDirGemini = $baseOut . '_gemini';
    $site->paths->outputDirGopher = $baseOut . '_gopher';
    $site->paths->outputDirMedia = $baseOut . '_media';
}
if (isset($config['contentdir'])) {
    $site->paths->contentDir = $config['contentdir'];
}
if (isset($config['active_theme']) && $config['active_theme'] !== 'default') {
    $site->paths->themeDir = \Indieinabox\Core\Database::$dataDir . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $config['active_theme'];
} elseif (isset($config['themedir'])) {
    $site->paths->themeDir = $config['themedir'];
}
if (isset($config['defaultcategory'])) {
    $site->support->defaultCategory = $config['defaultcategory'];
}
$site->localization->lang = $config['lang'];
$site->localization->defaultLang = $config['defaultlang'];
if (isset($config['htmlpostprocessing'])) {
    $site->options->htmlpostprocessing = $config['htmlpostprocessing'];
}
if (isset($config['prettylinks'])) {
    $site->options->prettylinks = (bool)$config['prettylinks'];
}

if (isset($config['skipstatic'])) {
    $site->options->skipStatic = (bool)$config['skipstatic'];
}
if (isset($config['forcestaticoverride'])) {
    $site->options->forceStaticOverride = (bool)$config['forcestaticoverride'];
}
if (isset($config['forcerebuild'])) {
    $site->options->forceRebuild = (bool)$config['forcerebuild'];
}
if (isset($config['skipmedia'])) {
    $site->options->skipMedia = (bool)$config['skipmedia'];
}
if (isset($config['skippages'])) {
    $site->options->skipPages = (bool)$config['skippages'];
}

if (isset($config['twtxt'])) {
    $twtxtData = $config['twtxt'];
    $site->twtxt->nick = (string) ($twtxtData['nick'] ?? '');
    $site->twtxt->description = (string) ($twtxtData['description'] ?? '');
    $site->twtxt->avatar = (string) ($twtxtData['avatar'] ?? '');
    $site->twtxt->following = (array) ($twtxtData['following'] ?? []);
    $site->twtxt->hubs = (array) ($twtxtData['hubs'] ?? []);
}

if (php_sapi_name() === 'cli') {
    $kernel = new \Indieinabox\Console\ConsoleKernel($site);
    $exitCode = $kernel->handle($argv);
    exit($exitCode);
} else {
    $router = new \Indieinabox\Http\WebRouter($site);
    $router->handleRequest();
}
