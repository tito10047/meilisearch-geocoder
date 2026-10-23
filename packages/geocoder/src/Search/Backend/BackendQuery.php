<?php

namespace Tito10047\Geocoder\Search\Backend;

use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * A full-text search of the documents by name.
 */
final readonly class BackendQuery
{
    /**
     * @param string             $text      the words to search: "Hlavná Košice"
     * @param positive-int       $limit     the maximum number of documents
     * @param list<string>       $countries ISO 3166-1 alpha-2, upper case: only these countries; [] for all
     * @param list<DocumentKind> $kinds     only these kinds; [] for all
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public function __construct(
        public string $text,
        public int $limit,
        public array $countries = [],
        public array $kinds = [],
    ) {
        if ('' === trim($text)) {
            throw new InvalidArgumentException('The text to search must not be empty.');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException(\sprintf('The limit must be positive, %d given.', $limit));
        }
        if (!array_is_list($countries)) {
            throw new InvalidArgumentException('The countries must be a list.');
        }
        foreach ($countries as $country) {
            if (!\is_string($country) || 1 !== preg_match('/^[A-Z]{2}$/D', $country)) {
                throw new InvalidArgumentException(\sprintf('A country must be an ISO 3166-1 alpha-2 code in upper case, %s given.', get_debug_type($country)));
            }
        }
        if (!array_is_list($kinds)) {
            throw new InvalidArgumentException('The kinds must be a list.');
        }
        foreach ($kinds as $kind) {
            if (!$kind instanceof DocumentKind) {
                throw new InvalidArgumentException(\sprintf('The kinds must be instances of %s.', DocumentKind::class));
            }
        }
    }
}
