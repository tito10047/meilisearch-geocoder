<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\InvalidDumpException;
use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Reads the records of a Photon dump ("Nominatim Dump File") as typed objects.
 */
interface DumpReaderInterface
{
    /**
     * The records of the dump, lazily: the header first, then the countries and the places.
     *
     * A record of an unknown type is skipped, so that newer dumps of the same version can be read.
     *
     * @param string $dump a file path or a stream URL ("php://stdin"); a ".zst" file is decompressed
     *
     * @return iterable<int, DumpHeader|CountryInfo|Place>
     *
     * @throws InvalidArgumentException when the dump cannot be opened
     * @throws InvalidDumpException     when a line is not valid JSON, the header is missing or of an unsupported version, or a record lacks a required field
     * @throws RuntimeException         when reading the dump fails
     */
    public function read(string $dump): iterable;
}
