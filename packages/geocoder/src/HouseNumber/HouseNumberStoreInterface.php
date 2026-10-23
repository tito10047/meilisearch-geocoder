<?php

namespace Tito10047\Geocoder\HouseNumber;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\StreetKey;

/**
 * Finds the house numbers of a street while the user types one.
 */
interface HouseNumberStoreInterface
{
    /**
     * The house numbers of the street starting with the typed number ("12" finds "12", "12a",
     * "12/31"), and those whose orientation number starts with it ("42" finds "606/42"); the exact
     * matches first.
     *
     * @param positive-int $limit
     *
     * @return list<HouseNumber>
     *
     * @throws GeocoderUnavailableException when the database cannot answer
     */
    public function find(StreetKey $streetKey, string $number, int $limit): array;
}
