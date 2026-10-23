<?php

namespace Tito10047\Geocoder\Index\Exception;

use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Thrown when a request to Meilisearch fails: unreachable, rejected or answered unexpectedly.
 *
 * The exception of the Meilisearch client or of the HTTP client is the previous exception.
 */
final class MeilisearchException extends RuntimeException
{
}
