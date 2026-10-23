<?php

namespace Tito10047\Geocoder\Name;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;

/**
 * Lists the names of the imported countries, for recognizing them in a typed text ("Košice, Slovensko").
 */
interface CountryNameSourceInterface
{
    /**
     * Every known name of every country whose places were imported, in every language stored: the
     * translations (NameKind::Country, the name being the country code), the country's own name
     * (Translation::LOCAL_LANGUAGE) included where the import stored it.
     *
     * A country that was not imported is left out: its names are words of the imported names
     * ("Santa Lucia", "Monaco", "Malta") rather than a country anyone looks for in the index.
     *
     * @return list<Translation>
     *
     * @throws GeocoderUnavailableException when the names cannot be read
     */
    public function countryNames(): array;
}
