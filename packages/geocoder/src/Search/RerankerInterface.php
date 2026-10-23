<?php

namespace Tito10047\Geocoder\Search;

/**
 * Reorders the suggestions found by Meilisearch, e.g. to prefer the country of the user.
 */
interface RerankerInterface
{
    /**
     * @param list<RankedSuggestion> $suggestions the order of Meilisearch, best first, with the rank of their documents
     *
     * @return list<RankedSuggestion> the same suggestions, reordered
     */
    public function rerank(array $suggestions, SearchOptions $options): array;
}
