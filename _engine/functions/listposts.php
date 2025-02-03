<?php

function listposts()
{
    global $base, $pages;
    $localpages = $pages;
    $localpages = array_filter($localpages, "removegeneric");
    usort(
        $localpages,
        function ($a, $b) {
            return $b['date'] <=> $a['date'];
        }
    );
    $count = 0;
    ob_start();
    foreach ($localpages as $page) {
        include $base . DS . "_template/includes/summary.php";
        $count++;
        if ($count >= 10) {
            break;
        };
    }
    $return = ob_get_clean();
    return $return;
}
function removegeneric($var)
{
    if (isset($var["kind"])) {
        if ($var["kind"] !== "generic" && $var["kind"] !== "page") {
            return true;
        }
    }
    return false;
}
