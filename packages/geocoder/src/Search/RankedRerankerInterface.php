<?php

namespace Tito10047\Geocoder\Search;

/**
 * A reranker that also uses the rank of the documents in the index.
 *
 * The geocoder calls rerankRanked() instead of rerank() on rerankers implementing it; the rank
 * stays out of the public Suggestion this way.
 */
interface RankedRerankerInterface extends RerankerInterface
{
    /**
     * @param list<RankedSuggestion> $suggestions the order of the search engine, best first
     *
     * @return list<RankedSuggestion> the same suggestions, reordered
     */
    public function rerankRanked(array $suggestions, SearchOptions $options): array;
}
