<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Search\Backend\BackendQuery;
use Tito10047\Geocoder\Search\Backend\DocumentSourceInterface;
use Tito10047\Geocoder\Search\Backend\SearchBackendInterface;
use Tito10047\Geocoder\Search\Backend\SearchHit;

/**
 * Answers every search with the same hits (cut to the limit) and remembers the queries; reads
 * the documents of the hits by id.
 */
final class FakeSearchBackend implements SearchBackendInterface, DocumentSourceInterface
{
    /**
     * @var list<BackendQuery>
     */
    public array $queries = [];

    /**
     * @var list<SearchHit>
     */
    private readonly array $hits;

    /**
     * @param list<Document|SearchHit> $hits a document is a hit without the number of matching words
     */
    public function __construct(array $hits = [])
    {
        $this->hits = array_map(static fn (Document|SearchHit $hit): SearchHit => $hit instanceof SearchHit ? $hit : new SearchHit($hit), $hits);
    }

    public function search(BackendQuery $query): array
    {
        $this->queries[] = $query;

        return \array_slice($this->hits, 0, $query->limit);
    }

    public function document(string $id): ?Document
    {
        foreach ($this->hits as $hit) {
            if ($hit->document->id === $id) {
                return $hit->document;
            }
        }

        return null;
    }
}
