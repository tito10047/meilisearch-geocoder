<?php

namespace Tito10047\Geocoder\Search\Backend;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * A document found by the search engine, with how well it matched.
 */
final readonly class SearchHit
{
    /**
     * @param int|null $matchingWords the number of words of the query the document matched; null when the engine does not tell
     *
     * @throws InvalidArgumentException when the number of words is negative
     */
    public function __construct(
        public Document $document,
        public ?int $matchingWords = null,
    ) {
        if (null !== $matchingWords && $matchingWords < 0) {
            throw new InvalidArgumentException(\sprintf('The number of matching words must not be negative, %d given.', $matchingWords));
        }
    }
}
