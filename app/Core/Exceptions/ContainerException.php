<?php

declare(strict_types=1);

namespace Indieinabox\Core\Exceptions;

use Exception;
use Psr\Container\ContainerExceptionInterface;

/**
 * Generic exception thrown when container encounters an internal error.
 */
class ContainerException extends Exception implements ContainerExceptionInterface
{
}
