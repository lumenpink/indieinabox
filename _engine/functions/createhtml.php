<?php

/*
* @var array<string>
*/
function createHTMLFile(array $page): void
{
    global $base, $site, $pages, $p, $kinds;

    $p = $page;

    if (in_array("draft", $page["tags"])) {
        return;
    }

    $destination = str_replace("/", DS, $page["slug"]);
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

    echo "Built " . $page["slug"] . "index.html" . "\n";
    ob_start();
    include $base . DS . "_template/" . $page["layout"] . ".php";
    $fileContent = ob_get_clean();
    if (isset($site->htmlpostprocessing)) {
        if ($site->htmlpostprocessing == "beautify" || $site->dev == true) {
            $fileContent = beautifyhtml($fileContent);
        }
        if ($site->htmlpostprocessing == "minify" && $site->dev == false) {
            $fileContent = minifyhtml($fileContent);
        }
    }
    $file = fopen($destination, "w");
    fwrite($file, $fileContent);
    fclose($file);
}
