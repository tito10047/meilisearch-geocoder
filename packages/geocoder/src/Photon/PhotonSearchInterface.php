<?php

namespace Tito10047\Geocoder\Photon;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * Answers the searches of the Photon API ("/api") from the geocoder.
 */
interface PhotonSearchInterface
{
    /**
     * @return FeatureCollection the best match first; empty when nothing matches
     *
     * @throws InvalidArgumentException     when the text is not valid UTF-8
     * @throws GeocoderUnavailableException when Meilisearch or the database cannot answer
     */
    public function search(PhotonQuery $query): FeatureCollection;
}
