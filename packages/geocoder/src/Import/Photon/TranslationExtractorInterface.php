<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Name\Translation;

/**
 * Finds the translated names of towns, regions and countries in a dump, for the labels of results.
 */
interface TranslationExtractorInterface
{
    /**
     * What an object's address says of the translations of its town, part of the town, county and
     * region: candidates, not facts. Those equal to the local name are included (the name is not
     * translated in that language); the converter keeps, for each name and language, the
     * translation most addresses agree on.
     *
     * @return list<Translation>
     */
    public function fromPlace(Place $place): array;

    /**
     * The translations of the object's own name when it is a town or a part of a town, those equal
     * to the name included (also for a language it has no name in): the names a town gives itself.
     * The converter falls back to them for a name and a language no address translates.
     *
     * @return list<Translation>
     */
    public function fromOwnNames(Place $place): array;

    /**
     * The translations of the name of a country; the name they translate is the country code.
     *
     * @return list<Translation>
     */
    public function fromCountry(Country $country): array;
}
