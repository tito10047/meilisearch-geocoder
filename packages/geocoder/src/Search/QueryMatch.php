<?php

namespace Tito10047\Geocoder\Search;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * How many words of the searched text a suggestion matched.
 *
 * The words are those the search engine searched: the typed text without the house number
 * ("Hlavná 12, Košice" has the two words "Hlavná" and "Košice"), split at spaces and punctuation
 * ("Champs-Élysées, Paris" has three). A word matched with a typo or as the beginning of a longer
 * word (the last one, still being typed) counts as matched. Meilisearch searches the first 10
 * words of a text only, so a longer text has 10.
 *
 * "Champs-Élysées, Paris" finds the Champs-Élysées of Rust (Germany) too, matching 2 of the 3
 * words: an application falling back to another geocoder when the address lies outside the
 * imported countries checks isComplete(), or asks for complete matches only
 * (SearchOptions::withCompleteMatchesOnly()).
 */
final readonly class QueryMatch
{
    /**
     * @param int $matchedWords the number of words of the searched text the suggestion matched
     * @param int $queryWords   the number of words of the searched text
     *
     * @throws InvalidArgumentException when a number is out of range
     */
    public function __construct(
        public int $matchedWords,
        public int $queryWords,
    ) {
        if ($queryWords < 1) {
            throw new InvalidArgumentException(\sprintf('The number of words of a query must be positive, %d given.', $queryWords));
        }
        if ($matchedWords < 0 || $matchedWords > $queryWords) {
            throw new InvalidArgumentException(\sprintf('The number of matched words must be between 0 and %d, %d given.', $queryWords, $matchedWords));
        }
    }

    /**
     * Whether every word of the searched text matched.
     */
    public function isComplete(): bool
    {
        return $this->matchedWords === $this->queryWords;
    }
}
