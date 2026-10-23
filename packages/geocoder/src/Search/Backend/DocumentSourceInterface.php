<?php

namespace Tito10047\Geocoder\Search\Backend;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;

/**
 * Reads a document of the search engine by its id.
 */
interface DocumentSourceInterface
{
    /**
     * @param string $id "R109166", "s791028875740832172"
     *
     * @return Document|null null when there is no such document
     *
     * @throws GeocoderUnavailableException when the search engine cannot answer or answers nonsense
     */
    public function document(string $id): ?Document;
}
