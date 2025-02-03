<?php

// I know there is intl module but I'm not sure if it's available in all servers
function localizeddate($page)
{
    global $originaldaysofweek, $originalmonths, $intl;
    setlocale(LC_TIME, 'en-us');
    $epoch = $page["date"];
    if (is_float($epoch)) {
        $epoch = intval($epoch);
    }
    if (is_int($epoch)) {
        $epoch = strval($epoch);
        $date = DateTime::createFromFormat("U", $epoch);
    } else {
        $date = new DateTime($epoch);
    }


    $date->setTimezone(new DateTimeZone("America/Sao_Paulo"));
    $isoformat = date_format($date, 'c');
    $longformat = date_format($date, $intl[$page["lang"]]["localizeddate"]["full"]);
    // Change America/Sao_Paulo to short timezone
    $longformat = str_replace("America/Sao_Paulo", ($date->format('I') == '1') ? 'BRST' : 'BRT', $longformat);
    $longformat = str_replace($originaldaysofweek, $intl[$page["lang"]]["localizeddate"]["daysofweek"], $longformat);
    $longformat = str_replace($originalmonths, $intl[$page["lang"]]["localizeddate"]["months"], $longformat);
    return [
        "long" => $longformat,
        "iso" => $isoformat
    ];
    ;
}
