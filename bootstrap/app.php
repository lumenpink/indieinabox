<?php

declare(strict_types=1);

if (!defined('DS')) {
    // @codeCoverageIgnoreStart
    define('DS', DIRECTORY_SEPARATOR);
    // @codeCoverageIgnoreEnd
}

// @codeCoverageIgnoreStart
if (!class_exists(\Indieinabox\Bootstrap::class, false)) {
    require_once dirname(__DIR__) . '/app/Bootstrap.php';
}
// @codeCoverageIgnoreEnd

\Indieinabox\Bootstrap::run(dirname(__DIR__));
