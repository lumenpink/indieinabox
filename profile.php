<?php
$start = microtime(true);
require 'build.php';
$end = microtime(true);
echo "Total time: " . ($end - $start) . "s\n";
