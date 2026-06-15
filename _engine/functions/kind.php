<?php

/**
 * @param \Indieinabox\Page|array $page
 * @return array
 */
function kind($page): array
{
    global $site, $kindspath;
    $isObject = $page instanceof \Indieinabox\Page;
    $pageKind = $isObject ? $page->kind : ($page["kind"] ?? null);
    $pageSlug = $isObject ? $page->slug : ($page["slug"] ?? "");
    $pageLang = $isObject ? $page->lang : ($page["lang"] ?? "en");

    if ($pageKind !== null && $pageKind !== "") {
        $kind = $pageKind;
        $localizedkind = $pageKind;
    } else {
        $localizedkind = explode("/", $pageSlug);
        if ($pageLang == $site->defaultlang) {
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
