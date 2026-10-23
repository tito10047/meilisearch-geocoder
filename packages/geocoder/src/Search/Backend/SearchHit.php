<?php

namespace Tito10047\Geocoder\Search\Backend;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\QueryMatch;

/**
 * A document found by the search engine, with how well it matched.
 */
final readonly class SearchHit
{
    /**
     * @param int|null $matchingWords the number of words of the query the document matched; null when the engine does not tell
     * @param int|null $queryWords    the number of words of the query, as the engine split it; null when the engine does not tell
     *
     * @throws InvalidArgumentException when a number of words is out of range
     */
    public function __construct(
        public Document $document,
        public ?int $matchingWords = null,
        public ?int $queryWords = null,
    ) {
        if (null !== $matchingWords && $matchingWords < 0) {
            throw new InvalidArgumentException(\sprintf('The number of matching words must not be negative, %d given.', $matchingWords));
        }
        if (null !== $queryWords && $queryWords < 1) {
            throw new InvalidArgumentException(\sprintf('The number of words of the query must be positive, %d given.', $queryWords));
        }
        if (null !== $matchingWords && null !== $queryWords && $matchingWords > $queryWords) {
            throw new InvalidArgumentException(\sprintf('The document cannot match %d words of a query of %d.', $matchingWords, $queryWords));
        }
    }

    /**
     * How well the document matched; null unless both numbers are known.
     */
    public function match(): ?QueryMatch
    {
        return null === $this->matchingWords || null === $this->queryWords ? null : new QueryMatch($this->matchingWords, $this->queryWords);
    }
}
