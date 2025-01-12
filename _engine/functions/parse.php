<?php
// Function to serve a Markdown file as HTML

function parse($file)
{
    global $parsedown, $site, $kinds, $urltranslations, $pages;
    global $base, $front, $http_base;

    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $filename = pathinfo($file, PATHINFO_FILENAME);

    if (file_exists($file) && is_readable($file)) {
        if (!in_array($ext, $site->support)) {
            return false;
        }
        $content = file_get_contents($file);

        $frontMatter = [];
        if (preg_match('/^---\s*\n(.*?\n)---\s*\n/sm', $content, $matches)) {
            $yaml = new \Alchemy\Component\Yaml\Yaml();
            $frontMatter = $yaml->loadString($matches[1]);
            $content = substr($content, strlen($matches[0]));
        }
        $page = $frontMatter;

        if (!isset($page["title"])) {
            if (preg_match('/^# (.+?)$/m', $content, $matches)) {
                $page["title"] = $matches[1];
                $content = substr($content, strlen($matches[0]));
            } else {
                $page["title"] = $site->defaulttitle;
            }
        }

        $page["title"] = trim($page["title"]);

        if (!isset($page["date"])) {
            $page["date"] = filemtime($file);
        }

        /* Extract tags */

        preg_match_all("/(?<!\\\\)\s#\w+/", $content, $tagmatches);

        // Get tags from the markdown
        $tags = array_map(function ($tag) {
            $tag = trim($tag);
            $tag = ltrim($tag, "#");
            return $tag;
        }, $tagmatches[0]);

        if (!isset($page["tags"])) {
            $page["tags"] = [];
        } elseif (!is_array($page["tags"])) {
            $page["tags"] = (array) $page["tags"];
        }

        $page["tags"] = array_merge($page["tags"], $tags);
        $page["tags"] = array_map("strtolower", $page["tags"]);
        $page["tags"] = array_unique($page["tags"]);
        /*Remove lines that contains only tags */
        $content = preg_replace('/^(?:\s*#\w+\s*?)*$/m', "", $content);
        /* Add trailing slashes to all internal links for consistence */
        $content = preg_replace_callback(
            "/\[(.*?)\]\((.*?)\)/",
            function ($matches) {
                $link = $matches[2];
                $path_info = pathinfo($link);

                // Check if the link has no extension
                if (!isset($path_info["extension"])) {
                    $link = rtrim($link, "/") . "/";
                }

                return "[" . $matches[1] . "](" . $link . ")";
            },
            $content
        );

        $content = $parsedown->text($content);
        $page["content"] = trim($content, " \n\r\t");
        if (!$site->buildall) {
            /* Only parse if file has front matter */
            if (sizeof($page) == 0) {
                return;
            }
        }
        $slug = str_replace($base, "", $file);
        $slug = ltrim($slug, DS);
        $slug = preg_replace("/^" . $site->contentdir . "/", "", $slug);


        if ($filename == "index") {
            $slug = str_replace($filename . "." . $ext, "", $slug);
        } else {
            $slug = str_replace("." . $ext, "", $slug);
        }

        if (isset($page["slug"])) {
            $slug = str_replace($filename, $page["slug"], $slug);
        }
        $slug = trim($slug, DS);
        $slug = str_replace(DS, "/", $slug);
        $slug = strtolower($slug);
        /*Adding a trailing slash to be consistent in URL scheme */
        $slug = rtrim($slug, "/") . "/";
        $page["slug"] = $slug;
        echo "Parsing " . $slug . "\n";
        /*Note on slug
        Slug is a relative url with trailing slash but not preceded by a slash
        */

        $page["relpath"] = "";
        $relpath = rtrim($page["slug"], '/');
        $relpath = explode('/', $relpath);
        if (count($relpath) == 0 || $page['slug'] == '/') {
            $page["relpath"] = './';
        } else {
            for ($i = 0; $i < count($relpath); $i++) {
                $page["relpath"] .= '../';
            }
        }

        $fileContent = "";

        /* Making page as default layout */
        $layout = "page";

        if (isset($page["layout"])) {
            $file = $base . DS . "_template" . DS . $page["layout"] . ".php";
            if (file_exists($file) && is_readable($file)) {
                $layout = $page["layout"];
            }
        } else {
            /* If page layout is not set, the folder name will be the layout */
            $slug_parts = explode("/", trim($slug, "/"));
            if (sizeof($slug_parts) > 1) {
                $folder_name = trim($slug_parts[sizeof($slug_parts) - 2]);
                $file = $base . DS . "_template" . DS . $folder_name . ".php";
                if (file_exists($file) && is_readable($file)) {
                    $layout = $folder_name;
                }
            }
        }

        $page["layout"] = $layout;

        if (!isset($page["default-category"])) {
            $page["default-category"] = "General";
        }

        if (!isset($page["category"])) {
            $page["category"] = $page["default-category"];
        }

        if (!isset($page["lang"])) {
            if (!isset($site->lang) || empty($site->lang)) {
                $page["lang"] = "en";
            } elseif (is_array($site->lang)) {
                if (count($site->lang) == 1) {
                    $page["lang"] = $site->lang[0];
                } else {
                    if ($page["slug"] == "/") {
                        $page["lang"] = $site->lang[0];
                    } else {
                        $first = explode("/", $page["slug"])[0];
                        if (in_array($first, $site->lang)) {
                            $page["lang"] = $first;
                        } else {
                            $page["lang"] = $site->lang[0];
                        }
                    }
                }
            } else {
                $page["lang"] = $site->lang;
            }


            if (is_array($site->lang)) {
                $page["otherlang"] = $site->lang;
                array_splice($page["otherlang"], array_search($page["lang"], $page["otherlang"]), 1);
                foreach ($page["otherlang"] as $key => $value) {
                    if ($value == $site->defaultlang) {
                        $page["otherlangpath"][$key] = "";
                    } else {
                        $page["otherlangpath"][$key] = $value . "/";
                    }
                }
            } else {
                $page["otherlang"][] = $site->lang;
                $page["otherlangpath"][] = "";
            }
            if ($page["lang"] == $site->defaultlang) {
                $page["langpath"] = "";
            } else {
                $page["langpath"] = $page["lang"] . "/";
            }

            $page["nick"] = str_replace($page["lang"], '', $page["slug"]);
            $page["nick"] = explode("/", $page["nick"]);
            $page["nick"] = $page["nick"][count($page["nick"]) - 2];


            if (!isset($page["originalcontent"])) {
                if ($page["lang"] == $site->defaultlang) {
                    if ($page["slug"] == "/") {
                        $page["originalcontent"] = "index";
                    } else {
                        $page["originalcontent"] = $page["slug"];
                    }
                } else if ($page["nick"] == "") {
                    $page["originalcontent"] = "";
                } else {
                    $page["originalcontent"] = getoriginalcontent($page["nick"], $page["lang"]);
                }
            }

            if (!isset($page["langslug"])) {
                foreach ($page["otherlang"] as $key => $value) {
                    if ($value == $site->defaultlang) {
                        $page["langslug"][] = $page["originalcontent"];
                    } elseif ($page["originalcontent"] == "index") {
                        $page["langslug"][] = "";
                    } else {
                        if (isset($urltranslations[$page["originalcontent"]][$value])) {
                            $page["langslug"][] = $urltranslations[$page["originalcontent"]][$value];
                        } else {
                            $page["langslug"][] = $page["originalcontent"];
                        }
                    }
                }
            }
            [
                "localized" => $page["localizedkind"],
                "kind" =>  $page["kind"],
            ] = kind($page);
            [
                "long" => $page["localizeddate"],
                "iso" => $page["isodate"]
            ] = localizeddate($page);
        }
        foreach ($page as $key => $value) {
            $site->types[$key] = "";
        }
        return $page;
    }

    return false;
}
