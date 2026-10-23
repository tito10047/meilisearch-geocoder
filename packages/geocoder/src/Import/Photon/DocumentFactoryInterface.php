<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\HouseNumber\HouseNumber;

/**
 * Builds the documents and house numbers of the objects a selector chose.
 *
 * Every method returns null when the object lacks what the result needs (a name, a valid
 * country code, a house number).
 */
interface DocumentFactoryInterface
{
    /**
     * The document of a town, a village or a part of one.
     */
    public function place(Place $place): ?Document;

    /**
     * The document of a point of interest.
     */
    public function poi(Place $place): ?Document;

    /**
     * The document of the street a named way (one of its segments) belongs to.
     */
    public function street(Place $place): ?Document;

    /**
     * The document of the street an address names, for streets known only from their houses.
     */
    public function streetOfAddress(Place $place): ?Document;

    /**
     * The house number of an address.
     */
    public function houseNumber(Place $place): ?HouseNumber;
}
