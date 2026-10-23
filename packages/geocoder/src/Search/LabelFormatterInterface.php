<?php

namespace Tito10047\Geocoder\Search;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;

/**
 * Writes the line shown to the user for a suggestion: "Stephansplatz, Viedeň, Rakúsko".
 */
interface LabelFormatterInterface
{
    /**
     * @param Suggestion  $suggestion its name, city and region already in the requested language when known; its label is ignored
     * @param string|null $language   ISO 639-1, lower case; null for the local names
     *
     * @throws GeocoderUnavailableException when a translation cannot be looked up
     */
    public function format(Suggestion $suggestion, ?string $language): string;
}
