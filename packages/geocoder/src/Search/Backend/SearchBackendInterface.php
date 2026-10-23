<?php

namespace Tito10047\Geocoder\Search\Backend;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;

/**
 * The full-text search engine holding the documents (Meilisearch); the geocoder knows only this.
 */
interface SearchBackendInterface
{
    /**
     * @return list<SearchHit> the best match first, at most $query->limit
     *
     * @throws GeocoderUnavailableException when the search engine cannot answer or answers nonsense
     */
    public function search(BackendQuery $query): array;
}
