<?php

declare(strict_types=1);

namespace Indieinabox\Core\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Exception thrown when an entry or service is not found in the container.
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface
{
}
