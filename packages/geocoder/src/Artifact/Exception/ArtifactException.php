<?php

namespace Tito10047\Geocoder\Artifact\Exception;

use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Thrown when an artifact cannot be built or installed: the build is not live, a file is corrupt,
 * the versions differ, the Meilisearch instance is not empty, ….
 */
final class ArtifactException extends RuntimeException
{
}
