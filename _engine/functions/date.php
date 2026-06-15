<?php

declare(strict_types=1);

use Indieinabox\Page;
// I know there is intl module but I'm not sure if it's available in all servers
function localizeddate($page): array
{
    global $originaldaysofweek, $originalmonths, $intl;
    setlocale(LC_TIME, 'en-us');
    
    if ($page instanceof Page) {
        $epoch = $page->date;
        $lang = $page->localization->lang;
    } else {
        $epoch = $page["date"] ?? time();
        $lang = $page["lang"] ?? "en";
    }

    if ($epoch instanceof DateTime) {
        $date = $epoch;
    } else {
        if (is_float($epoch)) {
            $epoch = intval($epoch);
        }
        if (is_int($epoch) || (is_string($epoch) && is_numeric($epoch))) {
            $epoch = strval($epoch);
            $date = DateTime::createFromFormat("U", $epoch);
        } else {
            $date = new DateTime($epoch);
        }
    }


    $date->setTimezone(new DateTimeZone("America/Sao_Paulo"));
    $isoformat = date_format($date, 'c');
    $longformat = date_format($date, $intl[$lang]["localizeddate"]["full"]);
    // Change America/Sao_Paulo to short timezone
    $longformat = str_replace("America/Sao_Paulo", ($date->format('I') == '1') ? 'BRST' : 'BRT', $longformat);
    $longformat = str_replace($originaldaysofweek, $intl[$lang]["localizeddate"]["daysofweek"], $longformat);
    $longformat = str_replace($originalmonths, $intl[$lang]["localizeddate"]["months"], $longformat);
    return [
        "long" => $longformat,
        "iso" => $isoformat
    ];
}
