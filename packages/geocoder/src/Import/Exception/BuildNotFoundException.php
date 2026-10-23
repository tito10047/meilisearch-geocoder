<?php

namespace Tito10047\Geocoder\Import\Exception;

use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Thrown when a build does not exist (any more): its index or its tables are missing, or the
 * tables hold no metadata of a build.
 */
final class BuildNotFoundException extends RuntimeException
{
}
