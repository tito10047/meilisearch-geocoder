<?php

namespace Tito10047\Geocoder\Tests\Search\Backend;

use Meilisearch\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Backend\BackendQuery;
use Tito10047\Geocoder\Search\Backend\MeilisearchSearchBackend;
use Tito10047\Geocoder\Tests\Fake\FakeHttpClient;

final class MeilisearchSearchBackendTest extends TestCase
{
    private const HIT = [
        'id' => 'R109166', 'kind' => 'place', 'type' => 'city', 'name' => 'Wien', 'city' => '', 'region' => '',
        'cc' => 'AT', 'postcode' => null, '_geo' => ['lat' => 48.2083537, 'lng' => 16.3725042], 'rank' => 90830,
        'names_i18n' => ['sk' => 'Viedeň'],
        '_rankingScoreDetails' => ['words' => ['order' => 0, 'matchingWords' => 1, 'maxMatchingWords' => 1, 'score' => 1.0]],
    ];

    public function testSearchesTheIndex(): void
    {
        $http = new FakeHttpClient($this->json(['hits' => [self::HIT], 'query' => 'wien']));

        $hits = $this->backend($http, 'geo_test')->search(new BackendQuery('wien', 5));

        $this->assertCount(1, $hits);
        $this->assertSame(1, $hits[0]->matchingWords);
        $document = $hits[0]->document;
        $this->assertSame('R109166', $document->id);
        $this->assertSame(DocumentKind::Place, $document->kind);
        $this->assertSame(['sk' => 'Viedeň'], $document->namesI18n);
        $this->assertSame(90830, $document->rank);

        $request = $http->requests[0];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/indexes/geo_test/search', $request->getUri()->getPath());
        $body = $this->body($request);
        $this->assertSame('wien', $body['q']);
        $this->assertSame(5, $body['limit']);
        $this->assertArrayNotHasKey('filter', $body);
        $this->assertIsArray($body['attributesToRetrieve']);
        $this->assertContains('names_i18n', $body['attributesToRetrieve']);
        $this->assertNotContains('names', $body['attributesToRetrieve']);
        $this->assertTrue($body['showRankingScoreDetails']);
    }

    /**
     * @param array<mixed>|null $details
     */
    #[DataProvider('provideScoreDetails')]
    public function testReadsTheMatchingWords(?array $details, ?int $expected): void
    {
        $hit = self::HIT;
        unset($hit['_rankingScoreDetails']);
        if (null !== $details) {
            $hit['_rankingScoreDetails'] = $details;
        }

        $hits = $this->backend(new FakeHttpClient($this->json(['hits' => [$hit]])))->search(new BackendQuery('wien', 5));

        $this->assertSame($expected, $hits[0]->matchingWords);
    }

    /**
     * @return iterable<string, array{array<mixed>|null, int|null}>
     */
    public static function provideScoreDetails(): iterable
    {
        yield 'matching words' => [['words' => ['matchingWords' => 2, 'maxMatchingWords' => 3]], 2];
        yield 'no details' => [null, null];
        yield 'no words rule' => [['typo' => ['score' => 1.0]], null];
        yield 'words not an object' => [['words' => 1], null];
        yield 'not an integer' => [['words' => ['matchingWords' => '2']], null];
        yield 'negative' => [['words' => ['matchingWords' => -1]], null];
    }

    public function testFiltersCountriesAndKinds(): void
    {
        $http = new FakeHttpClient($this->json(['hits' => []]));

        $this->backend($http)->search(new BackendQuery('hlavna', 15, ['SK', 'CZ'], [DocumentKind::Street, DocumentKind::Poi, DocumentKind::Street]));

        $this->assertSame(['cc IN ["SK", "CZ"]', 'kind IN ["street", "poi"]'], $this->body($http->requests[0])['filter']);
    }

    public function testCutsTheHitsToTheLimit(): void
    {
        $second = ['id' => 'R2'] + self::HIT;
        $http = new FakeHttpClient($this->json(['hits' => [self::HIT, $second]]));

        $this->assertCount(1, $this->backend($http)->search(new BackendQuery('wien', 1)));
    }

    public function testWrapsTheErrorsOfMeilisearch(): void
    {
        $http = new FakeHttpClient($this->json(['message' => 'Index `geo` not found.', 'code' => 'index_not_found'], 404));

        try {
            $this->backend($http)->search(new BackendQuery('wien', 5));
            $this->fail('No exception thrown.');
        } catch (GeocoderUnavailableException $e) {
            $this->assertStringContainsString('not found', $e->getMessage());
            $this->assertInstanceOf(\Meilisearch\Exceptions\ApiException::class, $e->getPrevious());
        }
    }

    public function testWrapsTheNetworkErrors(): void
    {
        $failure = new class('Connection refused') extends \RuntimeException implements NetworkExceptionInterface {
            public function getRequest(): RequestInterface
            {
                return (new Psr17Factory())->createRequest('POST', 'http://meilisearch');
            }
        };

        $this->expectException(GeocoderUnavailableException::class);
        $this->expectExceptionMessage('Connection refused');

        $this->backend(new FakeHttpClient($failure))->search(new BackendQuery('wien', 5));
    }

    /**
     * @param array<mixed> $answer
     */
    #[DataProvider('provideInvalidAnswers')]
    public function testRejectsInvalidAnswers(array $answer): void
    {
        $this->expectException(GeocoderUnavailableException::class);

        $this->backend(new FakeHttpClient($this->json($answer)))->search(new BackendQuery('wien', 5));
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function provideInvalidAnswers(): iterable
    {
        yield 'no hits' => [['query' => 'wien']];
        yield 'hits not a list' => [['hits' => ['a' => self::HIT]]];
        yield 'hit not an object' => [['hits' => ['R109166']]];
        yield 'invalid document' => [['hits' => [array_merge(self::HIT, ['kind' => 'x'])]]];
        yield 'document of another index' => [['hits' => [['id' => 1, 'title' => 'Dune']]]];
    }

    public function testReadsADocument(): void
    {
        $document = array_diff_key(self::HIT, ['_rankingScoreDetails' => true]) + ['suburb' => 'Innere Stadt', 'county' => 'Wien'];
        $http = new FakeHttpClient($this->json($document));

        $found = $this->backend($http, 'geo_test')->document('R109166');

        $this->assertInstanceOf(Document::class, $found);
        $this->assertSame('R109166', $found->id);
        $this->assertSame('Innere Stadt', $found->suburb);
        $this->assertSame('Wien', $found->county);
        $request = $http->requests[0];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/indexes/geo_test/documents/R109166', $request->getUri()->getPath());
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertIsString($query['fields'] ?? null);
        $this->assertContains('county', explode(',', $query['fields']));
        $this->assertNotContains('names', explode(',', $query['fields']));
    }

    public function testAMissingDocumentIsNone(): void
    {
        $http = new FakeHttpClient($this->json(['message' => 'Document `s1` not found.', 'code' => 'document_not_found'], 404));

        $this->assertNull($this->backend($http)->document('s1'));
    }

    public function testAnInvalidIdIsNoDocument(): void
    {
        $http = new FakeHttpClient($this->json([]));

        $this->assertNull($this->backend($http)->document('s1/../../keys'));
        $this->assertNull($this->backend($http)->document(''));
        $this->assertSame([], $http->requests);
    }

    public function testWrapsTheErrorsOfADocument(): void
    {
        $http = new FakeHttpClient($this->json(['message' => 'Index `geo` not found.', 'code' => 'index_not_found'], 404));

        $this->expectException(GeocoderUnavailableException::class);

        $this->backend($http)->document('s1');
    }

    public function testRejectsAnInvalidDocument(): void
    {
        $this->expectException(GeocoderUnavailableException::class);

        $this->backend(new FakeHttpClient($this->json(['id' => 1, 'title' => 'Dune'])))->document('s1');
    }

    #[DataProvider('provideInvalidIndexUids')]
    public function testRejectsAnInvalidIndexUid(string $indexUid): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->backend(new FakeHttpClient($this->json([])), $indexUid);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidIndexUids(): iterable
    {
        yield 'empty' => [''];
        yield 'slash' => ['geo/../keys'];
        yield 'space' => ['geo index'];
    }

    private function backend(FakeHttpClient $http, string $indexUid = 'geo'): MeilisearchSearchBackend
    {
        $factory = new Psr17Factory();

        return new MeilisearchSearchBackend(new Client('http://meilisearch', 'key', $http, $factory, [], $factory), $indexUid);
    }

    /**
     * @param array<mixed> $data
     */
    private function json(array $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(RequestInterface $request): array
    {
        $body = json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);

        /* @var array<string, mixed> $body */
        return $body;
    }
}
