<?php

declare(strict_types=1);

function copyStatic(string $dir): bool
{
    global $base, $site;

    if (!is_dir($dir)) {
        return false;
    }

    echo "Copying static files\n";
    copyStaticFiles($dir, $base, $site);

    if ($site->dev) {
        copyLiveJsFile($base, $site);
    }

    return true;
}

function copyStaticFiles(string $dir, string $base, object $site): void
{
    $entries = getDirContents($dir);

    foreach ($entries as $entry) {
        if (shouldSkipEntry($entry)) {
            continue;
        }

        $destination = getDestinationPath($entry, $dir, $base, $site);

        if (shouldCopyFile($entry, $destination, $site)) {
            ensureDestinationDirectoryExists($destination);
            copy($entry, $destination);
        }
    }
}

function shouldSkipEntry(string $entry): bool
{
    return $entry === "." || $entry === "..";
}

function getDestinationPath(string $entry, string $dir, string $base, object $site): string
{
    $path = str_replace($dir . DS, "", $entry);
    $filepath = pathinfo($path, PATHINFO_DIRNAME);
    $fullfilename = pathinfo($path, PATHINFO_BASENAME);

    return $base . DS . $site->outputdir . DS . $filepath . DS . $fullfilename;
}

function shouldCopyFile(string $source, string $destination, object $site): bool
{
    return is_file($source) && (!is_file($destination) || filemtime($source) > filemtime($destination) || $site->forcestaticoverride);
}

function ensureDestinationDirectoryExists(string $destination): void
{
    $directory = pathinfo($destination, PATHINFO_DIRNAME);

    if (!is_dir($directory)) {
        mkdir($directory, 0777, true); // true for recursive create
    }
}

function copyLiveJsFile(string $base, object $site): void
{
    $jsDir = $base . DS . $site->outputdir . DS . "js";

    if (!is_dir($jsDir)) {
        mkdir($jsDir, 0777, true); // true for recursive create
    }

    copy($base . "/_template/livejs/live.js", $jsDir . "/live.js");
}
