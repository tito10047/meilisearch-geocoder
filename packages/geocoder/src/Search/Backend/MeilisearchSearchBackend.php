<?php

namespace Tito10047\Geocoder\Search\Backend;

use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Exceptions\ExceptionInterface as MeilisearchException;
use Psr\Http\Client\ClientExceptionInterface;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * Searches the documents in a Meilisearch index, and reads them by id.
 */
final readonly class MeilisearchSearchBackend implements SearchBackendInterface, DocumentSourceInterface
{
    /** The ids Meilisearch accepts; any other cannot be a document. */
    private const DOCUMENT_ID = '/^[A-Za-z0-9_-]{1,511}$/D';

    /**
     * Only what a suggestion needs: the searched-only fields ("names", "cities") are the largest.
     */
    private const ATTRIBUTES = ['id', 'kind', 'type', 'name', 'names_i18n', 'suburb', 'city', 'county', 'region', 'cc', 'postcode', '_geo', 'rank'];

    /**
     * @param string $indexUid the index (or the alias swapped in by an import) holding the documents
     *
     * @throws InvalidArgumentException when the index uid is not valid
     */
    public function __construct(
        private Client $client,
        private string $indexUid = 'geo',
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,400}$/D', $indexUid)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a valid Meilisearch index uid: use letters, digits, hyphens and underscores.', $indexUid));
        }
    }

    public function search(BackendQuery $query): array
    {
        // the details tell how many words of the query each hit matched, for the reranker and the
        // complete matches
        $parameters = ['limit' => $query->limit, 'attributesToRetrieve' => self::ATTRIBUTES, 'showRankingScoreDetails' => true];
        $filter = $this->filter($query);
        if ([] !== $filter) {
            $parameters['filter'] = $filter;
        }

        try {
            $result = $this->client->index($this->indexUid)->rawSearch($query->text, $parameters);
        } catch (MeilisearchException|ClientExceptionInterface $e) {
            throw new GeocoderUnavailableException(\sprintf('Meilisearch cannot search the index "%s": %s', $this->indexUid, $e->getMessage()), 0, $e);
        }

        $hits = $result['hits'] ?? null;
        if (!\is_array($hits) || !array_is_list($hits)) {
            throw new GeocoderUnavailableException(\sprintf('Meilisearch answered a search of the index "%s" without a list of hits.', $this->indexUid));
        }

        $found = [];
        foreach ($hits as $hit) {
            if (!\is_array($hit)) {
                throw new GeocoderUnavailableException(\sprintf('Meilisearch answered a search of the index "%s" with a hit that is not an object.', $this->indexUid));
            }
            try {
                [$matchingWords, $queryWords] = $this->words($hit);
                $found[] = new SearchHit(Document::fromArray($hit), $matchingWords, $queryWords);
            } catch (InvalidArgumentException $e) {
                // a hit the geocoder cannot read means an index built for something else
                throw new GeocoderUnavailableException(\sprintf('The index "%s" holds an invalid document: %s', $this->indexUid, $e->getMessage()), 0, $e);
            }
        }

        return \array_slice($found, 0, $query->limit);
    }

    public function document(string $id): ?Document
    {
        // the client puts the id into the path as it is
        if (1 !== preg_match(self::DOCUMENT_ID, $id)) {
            return null;
        }

        try {
            $document = $this->client->index($this->indexUid)->getDocument($id, self::ATTRIBUTES);
        } catch (ApiException $e) {
            if ('document_not_found' === $e->errorCode) {
                return null;
            }

            throw new GeocoderUnavailableException(\sprintf('Meilisearch cannot read the document "%s" of the index "%s": %s', $id, $this->indexUid, $e->getMessage()), 0, $e);
        } catch (MeilisearchException|ClientExceptionInterface|\JsonException $e) {
            throw new GeocoderUnavailableException(\sprintf('Meilisearch cannot read the document "%s" of the index "%s": %s', $id, $this->indexUid, $e->getMessage()), 0, $e);
        }

        if (!\is_array($document)) {
            throw new GeocoderUnavailableException(\sprintf('Meilisearch answered the document "%s" of the index "%s" with something that is not an object.', $id, $this->indexUid));
        }
        try {
            return Document::fromArray($document);
        } catch (InvalidArgumentException $e) {
            throw new GeocoderUnavailableException(\sprintf('The index "%s" holds an invalid document: %s', $this->indexUid, $e->getMessage()), 0, $e);
        }
    }

    /**
     * The number of words of the query the hit matched and the number of words of the query, from
     * the details of the "words" ranking rule; null for what is missing or inconsistent (an index
     * ranked without the rule).
     *
     * "matchingWords" counts a word matched with a typo, as a prefix (the last word) or joined
     * with the next one ("mlynskenivy" for "Mlynské nivy"); "maxMatchingWords" is the number of
     * words Meilisearch searched: split at spaces and punctuation, 10 at most.
     *
     * @param array<array-key, mixed> $hit
     *
     * @return array{?int, ?int}
     */
    private function words(array $hit): array
    {
        $details = $hit['_rankingScoreDetails'] ?? null;
        $words = \is_array($details) ? ($details['words'] ?? null) : null;
        $matchingWords = \is_array($words) ? ($words['matchingWords'] ?? null) : null;
        $queryWords = \is_array($words) ? ($words['maxMatchingWords'] ?? null) : null;
        $matchingWords = \is_int($matchingWords) && $matchingWords >= 0 ? $matchingWords : null;
        $queryWords = \is_int($queryWords) && $queryWords >= 1 && (null === $matchingWords || $matchingWords <= $queryWords) ? $queryWords : null;

        return [$matchingWords, $queryWords];
    }

    /**
     * The filter expressions, combined with AND by Meilisearch.
     *
     * @return list<string>
     */
    private function filter(BackendQuery $query): array
    {
        $filter = [];
        if ([] !== $query->countries) {
            $filter[] = 'cc IN ['.implode(', ', array_map($this->quote(...), $query->countries)).']';
        }
        if ([] !== $query->kinds) {
            $kinds = array_values(array_unique(array_map(static fn (DocumentKind $kind): string => $kind->value, $query->kinds)));
            $filter[] = 'kind IN ['.implode(', ', array_map($this->quote(...), $kinds)).']';
        }

        return $filter;
    }

    /**
     * A value of a filter expression as a quoted string, so that no value can change the expression.
     */
    private function quote(string $value): string
    {
        return '"'.addcslashes($value, '"\\').'"';
    }
}
