<?php

namespace Tito10047\Geocoder\Photon;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * Turns the suggestions of the geocoder into features of the Photon API.
 */
interface FeatureFactoryInterface
{
    /**
     * @param string|null $language ISO 639 of the names (the suggestion is already in it), null for the local names
     *
     * @throws GeocoderUnavailableException when a name cannot be translated now
     */
    public function create(Suggestion $suggestion, ?string $language): Feature;
}
