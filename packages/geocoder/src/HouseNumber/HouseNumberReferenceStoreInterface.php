<?php

namespace Tito10047\Geocoder\HouseNumber;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;

/**
 * Finds a house number by the official id of its address (HouseNumber::$reference).
 */
interface HouseNumberReferenceStoreInterface
{
    /**
     * The house number with the reference, compared exactly; when several objects of the data
     * carry it (a building and its entrance), any one of them.
     *
     * @return HouseNumber|null null when no house number has it
     *
     * @throws GeocoderUnavailableException when the database cannot answer
     */
    public function findByReference(string $reference): ?HouseNumber;
}
