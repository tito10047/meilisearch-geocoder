<?php

namespace Tito10047\Geocoder\Import\Photon;

/**
 * The "CountryInfo" record of a dump: the names of the countries of the world.
 */
final readonly class CountryInfo
{
    /**
     * @param list<Country> $countries
     */
    public function __construct(
        public array $countries,
    ) {
    }
}
