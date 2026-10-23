<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Name\Translation;

/**
 * Finds the translated names of towns, regions and countries in a dump, for the labels of results.
 */
interface TranslationExtractorInterface
{
    /**
     * The translations of the town and the region of an object's address.
     *
     * @return list<Translation>
     */
    public function fromPlace(Place $place): array;

    /**
     * The translations of the name of a country; the name they translate is the country code.
     *
     * @return list<Translation>
     */
    public function fromCountry(Country $country): array;
}
