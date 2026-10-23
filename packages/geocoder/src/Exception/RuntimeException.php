<?php

namespace Tito10047\Geocoder\Exception;

/**
 * Thrown when an operation fails because of something outside the caller's control: a service,
 * a file, a database. The more specific exceptions of the geocoder extend it.
 */
class RuntimeException extends \RuntimeException implements ExceptionInterface
{
}
