<?php

namespace Tito10047\GeoBundle\Exception;

/**
 * The version of the Meilisearch instance an artifact is installed for is neither configured nor
 * readable from a running instance.
 */
final class UnknownMeilisearchVersionException extends \RuntimeException implements ExceptionInterface
{
}
