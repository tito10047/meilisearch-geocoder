<?php

namespace Tito10047\Geocoder\Import\Photon;

/**
 * A country of the "CountryInfo" record of a dump.
 */
final readonly class Country
{
    /**
     * @param string                $countryCode ISO 3166-1 alpha-2, upper case
     * @param array<string, string> $names       the OSM name tags: "name" => "Slovensko", "name:en" => "Slovakia", …
     */
    public function __construct(
        public string $countryCode,
        public array $names,
    ) {
    }
}
