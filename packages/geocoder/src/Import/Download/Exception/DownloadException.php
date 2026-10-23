<?php

namespace Tito10047\Geocoder\Import\Download\Exception;

use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Thrown when a dump cannot be downloaded: the server cannot be reached or refuses, the transfer
 * breaks, the file cannot be written or what arrived is not a Photon dump.
 */
final class DownloadException extends RuntimeException
{
}
