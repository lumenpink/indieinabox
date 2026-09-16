<?php

declare(strict_types=1);

if (!defined('DS')) {
    // @codeCoverageIgnoreStart
    define('DS', DIRECTORY_SEPARATOR);
    // @codeCoverageIgnoreEnd
}

// @codeCoverageIgnoreStart
if (!class_exists(\Indieinabox\Core\Bootstrap::class, false)) {
    require_once dirname(__DIR__) . '/app/Core/Bootstrap.php';
}
// @codeCoverageIgnoreEnd

\Indieinabox\Core\Bootstrap::run(dirname(__DIR__));
