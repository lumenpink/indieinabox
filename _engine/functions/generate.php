<?php

function generateHTMLFiles($pages)
{
    for ($i = 0; $i < sizeof($pages); $i++) {
        // echo "Building no. " . $i . " - " . $pages[$i]['slug'] . "\n";
        createHTMLFile($pages[$i]);
    }
}



function generateFeed()
{
    global $base, $pages, $site;

    $file = $base . DS . "_template" . DS . "feed" . ".php";
    if (file_exists($file) && is_readable($file)) {
        include $base . DS . "_template" . DS . "feed" . ".php";
    }
}
