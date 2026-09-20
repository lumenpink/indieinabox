<?php

declare(strict_types=1);

// Prevent PHP 8.4 deprecation notices from vendor libraries (such as mf2/mf2)
// from printing to STDOUT and triggering 'Cannot set response code - headers already sent'.
ini_set('display_errors', '0');

require __DIR__ . '/../vendor/autoload.php';
