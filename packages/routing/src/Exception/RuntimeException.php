<?php

namespace Tito10047\Routing\Exception;

/**
 * Thrown when an operation fails because of something outside the caller's control. The more
 * specific exceptions of the routing client extend it.
 */
class RuntimeException extends \RuntimeException implements ExceptionInterface
{
}
