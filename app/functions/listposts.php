<?php

/**
 * @return string|false
 */
function listposts()
{
    global $base, $pages;
    $localpages = $pages instanceof \Indieinabox\Pages ? $pages->all() : $pages;
    $localpages = array_filter($localpages, "removegeneric");
    usort(
        $localpages,
        function ($a, $b) {
            $dateA = $a instanceof \Indieinabox\Page ? $a->date : ($a['date'] ?? 0);
            $dateB = $b instanceof \Indieinabox\Page ? $b->date : ($b['date'] ?? 0);
            $timeA = $dateA instanceof \DateTime ? $dateA->getTimestamp() : $dateA;
            $timeB = $dateB instanceof \DateTime ? $dateB->getTimestamp() : $dateB;
            return $timeB <=> $timeA;
        }
    );
    $count = 0;
    ob_start();
    foreach ($localpages as $page) {
        include $base . DS . "resources/views/includes/summary.php";
        $count++;
        if ($count >= 10) {
            break;
        };
    }
    $return = ob_get_clean();
    return $return;
}
/**
 * @param mixed $var
 * @return bool
 */
function removegeneric($var): bool
{
    $kind = $var instanceof \Indieinabox\Page ? $var->kind : ($var["kind"] ?? null);
    if ($kind !== null) {
        if ($kind !== "generic" && $kind !== "page") {
            return true;
        }
    }
    return false;
}
