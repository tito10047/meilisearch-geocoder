<?php

namespace Tito10047\Geocoder\Name;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;

/**
 * Translates the names of towns, regions and countries for the labels of the suggestions.
 */
interface NameCatalogInterface
{
    /**
     * @param string $countryCode ISO 3166-1 alpha-2, upper case
     * @param string $name        the local name; for NameKind::Country, the country code
     * @param string $language    ISO 639-1, lower case
     *
     * @return string|null null when the catalog has no translation (the local name applies)
     *
     * @throws GeocoderUnavailableException when the catalog cannot answer
     */
    public function translate(string $countryCode, NameKind $kind, string $name, string $language): ?string;
}
