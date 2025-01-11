<?php
function copyAssets($dir)
{
    global $base, $site;
    $entries = scandir($dir);
    foreach ($entries as $entry) {
        if ($entry !== "." && $entry !== "..") {
            $path = $dir . DS . $entry;
            if (is_file($path)) {
                if (
                    pathinfo($path, PATHINFO_EXTENSION) == "js" ||
                    pathinfo($path, PATHINFO_EXTENSION) == "css"
                ) {
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    $filename = pathinfo($path, PATHINFO_FILENAME);

                    if (
                        !is_dir(
                            $base . DS . $site["output-dir"] . DS . "assets"
                        )
                    ) {
                        mkdir(
                            $base . DS . $site["output-dir"] . DS . "assets",
                            0777,
                            true
                        ); // true for recursive create
                    }

                    copy(
                        $path,
                        $base .
                            DS .
                            $site["output-dir"] .
                            DS .
                            "assets" .
                            DS .
                            $filename .
                            "." .
                            $ext
                    );
                }
            } else {
                copyAssets($path);
            }
        }
    }
}
