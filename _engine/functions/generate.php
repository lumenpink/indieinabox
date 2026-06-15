<?php

function generateHTMLFiles($pages)
{
    foreach ($pages as $page) {
        createHTMLFile($page);
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
