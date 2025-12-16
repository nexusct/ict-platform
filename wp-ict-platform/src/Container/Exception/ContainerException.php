<?php

declare(strict_types=1);

namespace ICT_Platform\Container\Exception;

use Psr\Container\ContainerExceptionInterface;
use Exception;

/**
 * Exception thrown when there is an error during container operations
 *
 * @package ICT_Platform\Container\Exception
 * @since   2.0.0
 */
class ContainerException extends Exception implements ContainerExceptionInterface
{
}
