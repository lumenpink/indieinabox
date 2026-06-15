<?php

declare(strict_types=1);

use Indieinabox\Page;

/**
 * @param Page $page
 */
function createHTMLFile(Page $page): void
{
    global $base, $site, $p, $kinds;

    $p = $page;

    if (in_array("draft", $page->metadata->tags)) {
        return;
    }

    $destination = str_replace("/", DS, $page->slug);
    $destination = trim($destination, DS);
    $destination = preg_replace(
        "/^" . $site->contentdir . "/",
        "",
        $destination
    );
    $destination = trim($destination, DS);

    if (!is_dir($base . DS . $site->outputdir . DS . $destination)) {
        mkdir($base . DS . $site->outputdir . DS . $destination, 0777, true); // true for recursive create
    }

    $destination =
        $base .
        DS .
        $site->outputdir .
        DS .
        $destination .
        DS .
        "index.html";

    echo "Built " . $page->slug . "index.html" . "\n";
    ob_start();
    // phpcs:ignore Generic.PHP.ForbiddenFunctions.FoundWithAlternative
    include_once $base . DS . "resources/views/" . $page->metadata->layout . ".php"; // NOSONAR
    $fileContent = ob_get_clean();
    if (isset($site->htmlpostprocessing)) {
        if ($site->htmlpostprocessing == "beautify" || $site->dev) {
            $fileContent = beautifyhtml($fileContent);
        }
        if ($site->htmlpostprocessing == "minify" && !$site->dev) {
            $fileContent = minifyhtml($fileContent);
        }
    }
    $file = fopen($destination, "w");
    fwrite($file, $fileContent);
    fclose($file);
}
