<?php

namespace Tito10047\Geocoder\Search\Query;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;

/**
 * Tells whether words typed into a query name a country: "Slovensko", "slovakia", "Česká republika".
 *
 * Where in the text a country may be typed is the parser's decision; the recognizer only knows the
 * names.
 */
interface CountryRecognizerInterface
{
    /**
     * The countries the whole phrase names, compared without diacritics, case and repeated spaces:
     * "Slovensko" and "SLOVENSKO" give ["SK"]; "Slovenská" (a part of a name) gives [].
     *
     * @return list<string> ISO 3166-1 alpha-2 codes, upper case; [] when the phrase names no country
     *
     * @throws GeocoderUnavailableException when the names cannot be read
     */
    public function countriesNamed(string $phrase): array;

    /**
     * The country of an ISO 3166-1 alpha-2 code typed in upper case ("SK"), when the recognizer
     * knows the country; null otherwise, "sk" included.
     *
     * @throws GeocoderUnavailableException when the names cannot be read
     */
    public function countryOfCode(string $code): ?string;
}
