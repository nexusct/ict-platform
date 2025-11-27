<?php

declare(strict_types=1);

namespace ICT_Platform\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;
use Exception;

/**
 * Exception thrown when a requested entry is not found in the container
 *
 * @package ICT_Platform\Container\Exception
 * @since   2.0.0
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface
{
}
