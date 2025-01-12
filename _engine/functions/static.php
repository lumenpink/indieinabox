<?php
function copyStatic($dir)
{
    global $base, $site;
    if (is_dir($dir)) {
        echo "Copying static files\n";
        $entries = getDirContents($dir);
        foreach ($entries as $entry) {
            if ($entry !== "." && $entry !== "..") {
                $path = str_replace($dir . DS, "", $entry);
                $filepath = pathinfo($path, PATHINFO_DIRNAME);
                $fullfilename = pathinfo($path, PATHINFO_BASENAME);
                $destination = $base . DS . $site->outputdir . DS . $filepath .
                    DS .
                    $fullfilename;
                if (
                    is_file($entry) &&
                    (!is_file($destination) ||
                        filemtime($entry) > filemtime($destination) ||
                        $site->forcestaticoverride)
                ) {
                    $filepath = pathinfo($path, PATHINFO_DIRNAME);
                    $fullfilename = pathinfo($path, PATHINFO_BASENAME);
                    if (
                        !is_dir(
                            $base . DS . $site->outputdir . DS . $filepath
                        )
                    ) {
                        mkdir(
                            $base . DS . $site->outputdir . DS . $filepath,
                            0777,
                            true
                        ); // true for recursive create
                    }
                    copy(
                        $entry,
                        $base .
                            DS .
                            $site->outputdir .
                            DS .
                            $filepath .
                            DS .
                            $fullfilename
                    );
                }
            }
        }
        if ($site->dev) {
            if (!is_dir($base . DS . $site->outputdir . DS . "js")) {
                mkdir($base . DS . $site->outputdir . DS . "js", 0777, true); // true for recursive create
            }
            copy(
                $base . "/_template/livejs/live.js",
                $base . DS . $site->outputdir . DS . "js/live.js"
            );
        }
    }
}
