<?php
require_once __DIR__ . '/bootstrap/app.php';

$zip = new ZipArchive();
$filename = "/tmp/my_test_theme.zip";

if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
    exit("cannot open <$filename>\n");
}

$zip->addFromString("test-theme-main/views/home.php", "<h1>Test Theme Home</h1>");
$zip->addFromString("test-theme-main/static/style.css", "body { background: black; }");
$zip->close();

$configHandler = new \Indieinabox\ConfigHandler(new \Indieinabox\Site());
// We can't call private method directly, so let's mock the upload.
$_FILES['custom_theme_zip'] = [
    'name' => 'my_test_theme.zip',
    'type' => 'application/zip',
    'tmp_name' => $filename,
    'error' => 0,
    'size' => filesize($filename),
];

$_SERVER['REQUEST_METHOD'] = 'POST';
// prevent redirect or actual save logic side effects if possible, but handle() will call saveConfig().
$configHandler->handle();

echo "Done.\n";
