<?php

declare(strict_types=1);

/**
 * @param iterable<\Indieinabox\Page> $pages
 */
function generateHTMLFiles(iterable $pages): void
{
    foreach ($pages as $page) {
        createHTMLFile($page);
    }
}



function generateFeed()
{
    global $base, $pages, $site;

    $file = $base . DS . "resources" . DS . "views" . DS . "feed" . ".php";
    if (file_exists($file) && is_readable($file)) {
        include $base . DS . "resources" . DS . "views" . DS . "feed" . ".php";
    }
}
