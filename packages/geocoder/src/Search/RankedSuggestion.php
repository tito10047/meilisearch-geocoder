<?php

namespace Tito10047\Geocoder\Search;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * A suggestion with the "rank" of the document it comes from and how well it matched, for the
 * rerankers that need them.
 *
 * The rank orders the documents of the index ("rank:desc": type of place × 1000 + importance × 999)
 * but means nothing to the application, so it is not a part of Suggestion.
 */
final readonly class RankedSuggestion
{
    /**
     * @param int      $rank          the rank of the document; an address has the rank of its street
     * @param int|null $matchingWords the number of words of the query the document matched; null when unknown
     *
     * @throws InvalidArgumentException when a number is negative
     */
    public function __construct(
        public Suggestion $suggestion,
        public int $rank,
        public ?int $matchingWords = null,
    ) {
        if ($rank < 0) {
            throw new InvalidArgumentException(\sprintf('The rank of a suggestion must not be negative, %d given.', $rank));
        }
        if (null !== $matchingWords && $matchingWords < 0) {
            throw new InvalidArgumentException(\sprintf('The number of matching words must not be negative, %d given.', $matchingWords));
        }
    }
}
