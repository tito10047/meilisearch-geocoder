<?php

namespace Tito10047\Geocoder\Import;

/**
 * What one Photon dump contained.
 */
final readonly class DumpSummary
{
    /**
     * @param string             $dump          the dump, as given to the converter
     * @param \DateTimeImmutable $dataTimestamp the "data_timestamp" of its header: the age of the OpenStreetMap data
     * @param list<string>       $countryCodes  ISO 3166-1 alpha-2, upper case: the countries of its documents
     */
    public function __construct(
        public string $dump,
        public \DateTimeImmutable $dataTimestamp,
        public array $countryCodes,
        public ImportCounts $counts,
    ) {
    }
}
