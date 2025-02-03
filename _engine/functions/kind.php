<?php

function kind($page): array
{
    global $site, $kindspath;
    if (isset($page["kind"])) {
        $kind = $page["kind"];
        $localizedkind = $page["kind"];
    } else {
        $localizedkind = explode("/", $page["slug"]);
        if ($page["lang"] == $site->defaultlang) {
            $localizedkind = $localizedkind[0];
        } else {
            $localizedkind = $localizedkind[1];
        }
        foreach ($kindspath as $key => $value) {
            if (in_array($localizedkind, $value)) {
                $kind = $key;
                break;
            }
        }
        if (!isset($kind)) {
            $kind = "generic";
            $localizedkind = "generic";
        }
    }
    return [
        "localized" => $localizedkind,
        "kind" => $kind,
    ];
}
