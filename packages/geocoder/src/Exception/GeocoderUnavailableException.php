<?php

namespace Tito10047\Geocoder\Exception;

/**
 * Thrown when the geocoder cannot answer now: Meilisearch or the database is unreachable or failing.
 *
 * Applications with a fallback geocoder switch to it when they catch this exception.
 */
class GeocoderUnavailableException extends RuntimeException
{
}
