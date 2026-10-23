<?php

namespace Tito10047\Geocoder\Tests\Index;

use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\Exception\MeilisearchException;
use Tito10047\Geocoder\Index\Exception\TaskFailedException;
use Tito10047\Geocoder\Index\Exception\TaskNotFoundException;
use Tito10047\Geocoder\Index\Exception\TaskTimeoutException;
use Tito10047\Geocoder\Index\Exception\WriterClosedException;
use Tito10047\Geocoder\Index\IndexingOptions;
use Tito10047\Geocoder\Index\MeilisearchDocumentWriter;
use Tito10047\Geocoder\Tests\Index\Fixtures\FakeHttpClient;
use Tito10047\Geocoder\Tests\Index\Fixtures\FakeNetworkException;

final class MeilisearchDocumentWriterTest extends TestCase
{
    private const DOCUMENTS = '/indexes/geo/documents?primaryKey=id';

    public function testSendsBatchesAndBoundsTheUnfinishedTasks(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('POST', self::DOCUMENTS, 10, 'documentAdditionOrUpdate', 'geo')
            ->expectTask('POST', self::DOCUMENTS, 11, 'documentAdditionOrUpdate', 'geo')
            // two batches unfinished: the third waits until the oldest is indexed
            ->expectTaskStatuses([10 => 'succeeded', 11 => 'processing'])
            ->expectTask('POST', self::DOCUMENTS, 12, 'documentAdditionOrUpdate', 'geo')
            ->expectTaskStatuses([11 => 'processing', 12 => 'enqueued'])
            ->expectTaskStatuses([11 => 'succeeded', 12 => 'succeeded']);
        $writer = new MeilisearchDocumentWriter($http->meilisearch(), 'geo', new IndexingOptions(batchSize: 2, maxPendingTasks: 2, pollInterval: 0.0));

        foreach (['a', 'b', 'c', 'd', 'e'] as $id) {
            $writer->write($this->document($id));
        }
        $this->assertCount(2, $http->requests);
        $writer->close();

        $this->assertSame(0, $http->remaining());
        $this->assertSame('application/x-ndjson', $http->request(0)->getHeaderLine('Content-Type'));
        $this->assertSame('Bearer secret', $http->request(0)->getHeaderLine('Authorization'));
        $lines = explode("\n", (string) $http->request(0)->getBody());
        $this->assertSame(['a', 'b', ''], array_map(static fn (string $line): string => '' === $line ? '' : json_decode($line, true, 512, \JSON_THROW_ON_ERROR)['id'], $lines));
        $this->assertSame("{\"id\":\"e\",\"kind\":\"street\",\"type\":\"street\",\"name\":\"Hlavná e\",\"names\":[\"Hlavná e\"],\"cities\":[\"Košice\",\"Kassa\"],\"suburb\":\"\",\"city\":\"Košice\",\"county\":\"\",\"region\":\"Košický kraj\",\"cc\":\"SK\",\"postcode\":null,\"_geo\":{\"lat\":48.7,\"lng\":21.25},\"rank\":25000,\"names_i18n\":{\"hu\":\"Fő utca\"}}\n", (string) $http->request(3)->getBody());
    }

    public function testOneTaskAtATime(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('POST', self::DOCUMENTS, 1, 'documentAdditionOrUpdate', 'geo')
            ->expectTaskStatuses([1 => 'succeeded'])
            ->expectTask('POST', self::DOCUMENTS, 2, 'documentAdditionOrUpdate', 'geo')
            ->expectTaskStatuses([2 => 'succeeded']);
        $writer = new MeilisearchDocumentWriter($http->meilisearch(), 'geo', new IndexingOptions(batchSize: 1, maxPendingTasks: 1, pollInterval: 0.0));

        $writer->write($this->document('a'));
        $writer->write($this->document('b'));
        $writer->close();

        $this->assertSame(0, $http->remaining());
    }

    public function testCloseWithoutDocumentsSendsNothing(): void
    {
        $http = new FakeHttpClient();
        $writer = new MeilisearchDocumentWriter($http->meilisearch(), 'geo');

        $writer->close();
        $writer->close();

        $this->assertSame([], $http->requests);
    }

    public function testFailedBatchFailsLoudly(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('POST', self::DOCUMENTS, 7, 'documentAdditionOrUpdate', 'geo')
            ->expectTaskStatuses([7 => 'failed']);
        $writer = new MeilisearchDocumentWriter($http->meilisearch(), 'geo', new IndexingOptions(pollInterval: 0.0));
        $writer->write($this->document('a'));

        try {
            $writer->close();
            $this->fail('The failed task was not reported.');
        } catch (TaskFailedException $e) {
            $this->assertSame(7, $e->task->uid);
            $this->assertSame('missing_document_id', $e->task->errorCode);
        }
    }

    public function testFailedBatchFailsTheNextWrite(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('POST', self::DOCUMENTS, 7, 'documentAdditionOrUpdate', 'geo')
            ->expectTaskStatuses([7 => 'failed']);
        $writer = new MeilisearchDocumentWriter($http->meilisearch(), 'geo', new IndexingOptions(batchSize: 1, maxPendingTasks: 1, pollInterval: 0.0));
        $writer->write($this->document('a'));

        $this->expectException(TaskFailedException::class);

        $writer->write($this->document('b'));
    }

    public function testCanceledBatchFails(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('POST', self::DOCUMENTS, 7, 'documentAdditionOrUpdate', 'geo')
            ->expectTaskStatuses([7 => 'canceled']);
        $writer = new MeilisearchDocumentWriter($http->meilisearch(), 'geo', new IndexingOptions(pollInterval: 0.0));
        $writer->write($this->document('a'));

        $this->expectException(TaskFailedException::class);

        $writer->close();
    }

    public function testUnknownTaskFails(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('POST', self::DOCUMENTS, 7, 'documentAdditionOrUpdate', 'geo')
            ->expect('GET', '/tasks/?uids=7&limit=1', ['results' => [], 'total' => 0, 'limit' => 1, 'from' => null, 'next' => null]);
        $writer = new MeilisearchDocumentWriter($http->meilisearch(), 'geo', new IndexingOptions(pollInterval: 0.0));
        $writer->write($this->document('a'));

        $this->expectException(TaskNotFoundException::class);
        $this->expectExceptionMessage('Meilisearch does not know the task 7:');

        $writer->close();
    }

    public function testGivesUpAfterTheTimeout(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('POST', self::DOCUMENTS, 7, 'documentAdditionOrUpdate', 'geo')
            ->expectTaskStatuses([7 => 'processing'])
            ->expectTaskStatuses([7 => 'processing']);
        $writer = new MeilisearchDocumentWriter($http->meilisearch(), 'geo', new IndexingOptions(taskTimeout: 0.05, pollInterval: 0.05));
        $writer->write($this->document('a'));

        try {
            $writer->close();
            $this->fail('The timeout was not reported.');
        } catch (TaskTimeoutException $e) {
            $this->assertSame([7], $e->taskUids);
        }
    }

    public function testUnreachableMeilisearch(): void
    {
        $http = (new FakeHttpClient())
            ->expectFailure('POST', self::DOCUMENTS, new FakeNetworkException(new Request('POST', 'http://meilisearch')));
        $writer = new MeilisearchDocumentWriter($http->meilisearch(), 'geo');
        $writer->write($this->document('a'));

        try {
            $writer->close();
            $this->fail('The failure was not reported.');
        } catch (MeilisearchException $e) {
            $this->assertStringContainsString('Connection refused', $e->getMessage());
            $this->assertInstanceOf(\Throwable::class, $e->getPrevious());
        }
    }

    public function testRejectedRequest(): void
    {
        $http = (new FakeHttpClient())
            ->expect('POST', self::DOCUMENTS, ['message' => 'The provided API key is invalid.', 'code' => 'invalid_api_key', 'type' => 'auth', 'link' => ''], 403);
        $writer = new MeilisearchDocumentWriter($http->meilisearch(), 'geo');
        $writer->write($this->document('a'));

        $this->expectException(MeilisearchException::class);
        $this->expectExceptionMessage('The provided API key is invalid.');

        $writer->close();
    }

    public function testNothingCanBeWrittenAfterClose(): void
    {
        $writer = new MeilisearchDocumentWriter((new FakeHttpClient())->meilisearch(), 'geo');
        $writer->close();

        $this->expectException(WriterClosedException::class);

        $writer->write($this->document('a'));
    }

    public function testRejectsAnInvalidUid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MeilisearchDocumentWriter((new FakeHttpClient())->meilisearch(), 'geo index');
    }

    private function document(string $id): Document
    {
        return new Document(
            id: $id,
            kind: DocumentKind::Street,
            type: 'street',
            name: 'Hlavná '.$id,
            names: ['Hlavná '.$id],
            cities: ['Košice', 'Kassa'],
            namesI18n: ['hu' => 'Fő utca'],
            city: 'Košice',
            region: 'Košický kraj',
            countryCode: 'SK',
            postcode: null,
            coordinates: new Coordinates(48.7, 21.25),
            rank: 25000,
        );
    }
}
