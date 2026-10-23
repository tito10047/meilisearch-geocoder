<?php

namespace Tito10047\Geocoder\Search;

/**
 * Reorders the suggestions found by Meilisearch, e.g. to prefer the country of the user.
 */
interface RerankerInterface
{
    /**
     * @param list<Suggestion> $suggestions the order of Meilisearch, best first
     *
     * @return list<Suggestion> the same suggestions, reordered
     */
    public function rerank(array $suggestions, SearchOptions $options): array;
}
