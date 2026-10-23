<?php

namespace Tito10047\Geocoder\Import\Photon;

/**
 * The first line of a Photon dump.
 */
final readonly class DumpHeader
{
    /**
     * @param string             $version         the version of the dump format, e.g. "0.1.0"
     * @param string             $generator       the program that wrote the dump, e.g. "photon"
     * @param \DateTimeImmutable $dataTimestamp   the age of the OpenStreetMap data
     * @param bool               $sortedByCountry whether the places come country after country
     */
    public function __construct(
        public string $version,
        public string $generator,
        public \DateTimeImmutable $dataTimestamp,
        public bool $sortedByCountry,
    ) {
    }
}
