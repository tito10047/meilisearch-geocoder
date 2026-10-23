<?php

namespace Tito10047\Geocoder\Index\Exception;

use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Thrown when a document is written to a writer that was already closed.
 */
final class WriterClosedException extends RuntimeException
{
}
