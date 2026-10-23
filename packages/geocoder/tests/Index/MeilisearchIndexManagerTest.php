<?php

namespace Tito10047\Geocoder\Tests\Index;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\Exception\MeilisearchException;
use Tito10047\Geocoder\Index\Exception\TaskFailedException;
use Tito10047\Geocoder\Index\Exception\TaskNotFoundException;
use Tito10047\Geocoder\Index\Exception\TaskTimeoutException;
use Tito10047\Geocoder\Index\IndexingOptions;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\IndexStatus;
use Tito10047\Geocoder\Index\MeilisearchDocumentWriter;
use Tito10047\Geocoder\Index\MeilisearchIndexManager;
use Tito10047\Geocoder\Tests\Index\Fixtures\FakeHttpClient;

final class MeilisearchIndexManagerTest extends TestCase
{
    private const BUILD_CREATED = '2026-10-23T12:00:00.123456789Z';
    private const LIVE_CREATED = '2026-10-16T12:00:00.987654321Z';
    private const NO_TASK = ['results' => [], 'total' => 0, 'limit' => 1, 'from' => null, 'next' => null];
    private const NOT_FOUND = ['message' => 'Index `geo` not found.', 'code' => 'index_not_found', 'type' => 'invalid_request', 'link' => ''];

    public function testCreateAppliesTheSettingsBeforeAnyDocument(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('POST', '/indexes', 1, 'indexCreation', 'geo_20261023120000')
            ->expectTask('PATCH', '/indexes/geo_20261023120000/settings', 2, 'settingsUpdate', 'geo_20261023120000')
            ->expectTaskStatuses([1 => 'succeeded', 2 => 'succeeded']);

        $this->manager($http)->create('geo_20261023120000', new IndexSettings());

        $this->assertSame(0, $http->remaining());
        $this->assertSame(['primaryKey' => 'id', 'uid' => 'geo_20261023120000'], $http->jsonBody(0));
        $this->assertSame((new IndexSettings())->toArray(), $http->jsonBody(1));
    }

    public function testCreateFailsWhenTheIndexExists(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('POST', '/indexes', 1, 'indexCreation', 'geo')
            ->expectTask('PATCH', '/indexes/geo/settings', 2, 'settingsUpdate', 'geo')
            ->expectTaskStatuses([1 => 'failed', 2 => 'succeeded']);

        $this->expectException(TaskFailedException::class);

        $this->manager($http)->create('geo', new IndexSettings());
    }

    public function testCreateRejectsAnInvalidUid(): void
    {
        $http = new FakeHttpClient();

        try {
            $this->manager($http)->create('geo/x', new IndexSettings());
            $this->fail('The invalid uid was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $http->requests);
        }
    }

    public function testApplySettings(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('PATCH', '/indexes/geo/settings', 3, 'settingsUpdate', 'geo')
            ->expectTaskStatuses([3 => 'processing'])
            ->expectTaskStatuses([3 => 'succeeded']);

        $this->manager($http)->applySettings('geo', new IndexSettings(maxTotalHits: 50));

        $this->assertSame(0, $http->remaining());
        $this->assertSame(['maxTotalHits' => 50], $http->jsonBody(0)['pagination']);
    }

    public function testDocumentWriter(): void
    {
        $this->assertInstanceOf(MeilisearchDocumentWriter::class, $this->manager(new FakeHttpClient())->documentWriter('geo'));
    }

    public function testWaitForTasksUntilNoneIsLeft(): void
    {
        $unfinished = '/tasks/?indexUids=geo&statuses=enqueued,processing&limit=1000';
        $http = (new FakeHttpClient())
            ->expect('GET', $unfinished, ['results' => [$this->task(3, 'processing'), $this->task(4, 'enqueued')]])
            ->expectTaskStatuses([3 => 'succeeded', 4 => 'succeeded'])
            // a task enqueued meanwhile
            ->expect('GET', $unfinished, ['results' => [$this->task(5, 'enqueued')]])
            ->expectTaskStatuses([5 => 'succeeded'])
            ->expect('GET', $unfinished, ['results' => []]);

        $this->manager($http)->waitForTasks('geo');

        $this->assertSame(0, $http->remaining());
    }

    public function testWaitForTasksReportsAFailedTask(): void
    {
        $http = (new FakeHttpClient())
            ->expect('GET', '/tasks/?indexUids=geo&statuses=enqueued,processing&limit=1000', ['results' => [$this->task(3, 'processing')]])
            ->expectTaskStatuses([3 => 'failed']);

        $this->expectException(TaskFailedException::class);
        $this->expectExceptionMessage('Meilisearch task 3 (documentAdditionOrUpdate on geo) failed');

        $this->manager($http)->waitForTasks('geo');
    }

    public function testCompact(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('POST', '/indexes/geo/compact', 6, 'indexCompaction', 'geo')
            ->expectTaskStatuses([6 => 'succeeded'], 'indexCompaction');

        $this->manager($http)->compact('geo');

        $this->assertSame(0, $http->remaining());
    }

    public function testSwapWithTheLiveIndex(): void
    {
        $http = (new FakeHttpClient())
            ->expect('GET', '/indexes/geo_20261023120000', $this->index('geo_20261023120000', self::BUILD_CREATED))
            ->expect('GET', '/indexes/geo', $this->index('geo', self::LIVE_CREATED))
            ->expectTask('POST', '/swap-indexes', 7, 'indexSwap', null)
            ->expectTaskStatuses([7 => 'succeeded'], 'indexSwap', null);

        $this->manager($http)->swap('geo_20261023120000', 'geo');

        $this->assertSame([['indexes' => ['geo_20261023120000', 'geo']]], $http->jsonBody(2));
        $this->assertSame(0, $http->remaining());
    }

    public function testFirstSwapRenames(): void
    {
        $http = (new FakeHttpClient())
            ->expect('GET', '/indexes/geo_20261023120000', $this->index('geo_20261023120000', self::BUILD_CREATED))
            ->expect('GET', '/indexes/geo', self::NOT_FOUND, 404)
            ->expectTask('POST', '/swap-indexes', 7, 'indexSwap', null)
            ->expectTaskStatuses([7 => 'succeeded'], 'indexSwap', null);

        $this->manager($http)->swap('geo_20261023120000', 'geo');

        $this->assertSame([['indexes' => ['geo_20261023120000', 'geo'], 'rename' => true]], $http->jsonBody(2));
    }

    public function testSwapFailsWhenTheBuildIsMissing(): void
    {
        $http = (new FakeHttpClient())->expect('GET', '/indexes/geo_20261023120000', self::NOT_FOUND, 404);

        $this->expectException(MeilisearchException::class);
        $this->expectExceptionMessage('Cannot swap the index geo_20261023120000 in: it does not exist.');

        $this->manager($http)->swap('geo_20261023120000', 'geo');
    }

    public function testSwapReportsAFailedSwap(): void
    {
        $http = (new FakeHttpClient())
            ->expect('GET', '/indexes/geo_20261023120000', $this->index('geo_20261023120000', self::BUILD_CREATED))
            ->expect('GET', '/indexes/geo', $this->index('geo', self::LIVE_CREATED))
            ->expectTask('POST', '/swap-indexes', 7, 'indexSwap', null)
            ->expectTaskStatuses([7 => 'failed'], 'indexSwap', null);

        $this->expectException(TaskFailedException::class);

        $this->manager($http)->swap('geo_20261023120000', 'geo');
    }

    public function testSwapHiddenFromTheKeyIsConfirmedByTheIndexes(): void
    {
        // a key restricted to "geo*" does not see the swap, a task of no index
        $http = (new FakeHttpClient())
            ->expect('GET', '/indexes/geo_20261023120000', $this->index('geo_20261023120000', self::BUILD_CREATED))
            ->expect('GET', '/indexes/geo', $this->index('geo', self::LIVE_CREATED))
            ->expectTask('POST', '/swap-indexes', 7, 'indexSwap', null)
            ->expect('GET', '/tasks/?uids=7&limit=1', self::NO_TASK)
            // not processed yet
            ->expect('GET', '/indexes/geo', $this->index('geo', self::LIVE_CREATED))
            // processed: the creation times moved with the indexes
            ->expect('GET', '/indexes/geo', $this->index('geo', self::BUILD_CREATED))
            ->expect('GET', '/indexes/geo_20261023120000', $this->index('geo_20261023120000', self::LIVE_CREATED));

        $this->manager($http)->swap('geo_20261023120000', 'geo');

        $this->assertSame(0, $http->remaining());
    }

    public function testRenameHiddenFromTheKeyIsConfirmedByTheIndexes(): void
    {
        $http = (new FakeHttpClient())
            ->expect('GET', '/indexes/geo_20261023120000', $this->index('geo_20261023120000', self::BUILD_CREATED))
            ->expect('GET', '/indexes/geo', self::NOT_FOUND, 404)
            ->expectTask('POST', '/swap-indexes', 7, 'indexSwap', null)
            ->expect('GET', '/tasks/?uids=7&limit=1', self::NO_TASK)
            ->expect('GET', '/indexes/geo', $this->index('geo', self::BUILD_CREATED))
            ->expect('GET', '/indexes/geo_20261023120000', self::NOT_FOUND, 404);

        $this->manager($http)->swap('geo_20261023120000', 'geo');

        $this->assertSame(0, $http->remaining());
    }

    public function testSwapHiddenFromTheKeyTimesOutWithoutItsEffect(): void
    {
        $http = (new FakeHttpClient())
            ->expect('GET', '/indexes/geo_20261023120000', $this->index('geo_20261023120000', self::BUILD_CREATED))
            ->expect('GET', '/indexes/geo', self::NOT_FOUND, 404)
            ->expectTask('POST', '/swap-indexes', 7, 'indexSwap', null)
            ->expect('GET', '/tasks/?uids=7&limit=1', self::NO_TASK);
        for ($i = 0; $i < 1000; ++$i) {
            $http->expect('GET', '/indexes/geo', self::NOT_FOUND, 404);
        }
        $manager = new MeilisearchIndexManager($http->meilisearch(), new IndexingOptions(taskTimeout: 0.05, pollInterval: 0.01));

        try {
            $manager->swap('geo_20261023120000', 'geo');
            $this->fail('A swap without effect was confirmed.');
        } catch (TaskTimeoutException $e) {
            $this->assertSame([7], $e->taskUids);
            $this->assertStringContainsString('task 7 (indexSwap) cannot be read with this API key, and its effect (the index geo_20261023120000 renamed to geo) did not appear', $e->getMessage());
        }
    }

    public function testSwapRejectsTheSameIndex(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager(new FakeHttpClient())->swap('geo', 'geo');
    }

    public function testDelete(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('DELETE', '/indexes/geo_20261023120000', 8, 'indexDeletion', 'geo_20261023120000')
            ->expectTaskStatuses([8 => 'succeeded'], 'indexDeletion', 'geo_20261023120000');

        $this->manager($http)->delete('geo_20261023120000');

        $this->assertSame(0, $http->remaining());
    }

    public function testExists(): void
    {
        $http = (new FakeHttpClient())
            ->expect('GET', '/indexes/geo', ['uid' => 'geo', 'primaryKey' => 'id'])
            ->expect('GET', '/indexes/geo', self::NOT_FOUND, 404);
        $manager = $this->manager($http);

        $this->assertTrue($manager->exists('geo'));
        $this->assertFalse($manager->exists('geo'));
    }

    public function testExistsFailsWhenMeilisearchFails(): void
    {
        $http = (new FakeHttpClient())
            ->expect('GET', '/indexes/geo', ['message' => 'The provided API key is invalid.', 'code' => 'invalid_api_key', 'type' => 'auth', 'link' => ''], 403);

        $this->expectException(MeilisearchException::class);

        $this->manager($http)->exists('geo');
    }

    public function testIndexUidsReadsEveryPage(): void
    {
        $first = array_map(static fn (int $i): array => ['uid' => 'index'.$i], range(1, 100));
        $http = (new FakeHttpClient())
            ->expect('GET', '/indexes?offset=0&limit=100', ['results' => $first, 'offset' => 0, 'limit' => 100, 'total' => 101])
            ->expect('GET', '/indexes?offset=100&limit=100', ['results' => [['uid' => 'geo']], 'offset' => 100, 'limit' => 100, 'total' => 101]);

        $uids = $this->manager($http)->indexUids();

        $this->assertCount(101, $uids);
        $this->assertSame('index1', $uids[0]);
        $this->assertSame('geo', $uids[100]);
    }

    public function testIndexUidsRejectsAMalformedAnswer(): void
    {
        $http = (new FakeHttpClient())->expect('GET', '/indexes?offset=0&limit=100', ['results' => [['name' => 'geo']], 'total' => 1]);

        $this->expectException(MeilisearchException::class);

        $this->manager($http)->indexUids();
    }

    public function testStatus(): void
    {
        $http = (new FakeHttpClient())
            ->expect('GET', '/indexes/geo/stats', ['numberOfDocuments' => 61_754, 'rawDocumentDbSize' => 17_965_056, 'avgDocumentSize' => 290, 'isIndexing' => false, 'fieldDistribution' => []])
            ->expect('GET', '/indexes/geo/settings', (new IndexSettings())->toArray() + ['stopWords' => [], 'distinctAttribute' => null])
            ->expect('GET', '/stats', ['databaseSize' => 93_323_264, 'usedDatabaseSize' => 80_000_000, 'indexes' => []])
            ->expect('GET', '/version', ['commitSha' => '47d0c34', 'commitDate' => 'unknown', 'pkgVersion' => '1.35.1']);

        $status = $this->manager($http)->status('geo');

        $this->assertEquals(new IndexStatus('geo', 61_754, 17_965_056, 93_323_264, false, (new IndexSettings())->hash(), '1.35.1'), $status);
    }

    public function testStatusOfAnIndexWithForeignSettings(): void
    {
        $settings = ['filterableAttributes' => ['price']] + (new IndexSettings())->toArray();
        $http = (new FakeHttpClient())
            ->expect('GET', '/indexes/products/stats', ['numberOfDocuments' => 3, 'rawDocumentDbSize' => 100, 'isIndexing' => true])
            ->expect('GET', '/indexes/products/settings', $settings)
            ->expect('GET', '/stats', ['databaseSize' => 1000])
            ->expect('GET', '/version', ['pkgVersion' => '1.35.1']);

        $status = $this->manager($http)->status('products');

        $this->assertNull($status->settingsHash);
        $this->assertTrue($status->indexing);
    }

    public function testStatusOfAMissingIndex(): void
    {
        $http = (new FakeHttpClient())->expect('GET', '/indexes/geo/stats', self::NOT_FOUND, 404);

        $this->expectException(MeilisearchException::class);
        $this->expectExceptionMessage('Index `geo` not found.');

        $this->manager($http)->status('geo');
    }

    public function testStatusRejectsMalformedStatistics(): void
    {
        $http = (new FakeHttpClient())->expect('GET', '/indexes/geo/stats', ['numberOfDocuments' => '3']);

        $this->expectException(MeilisearchException::class);

        $this->manager($http)->status('geo');
    }

    public function testFingerprintIsTheCreationTime(): void
    {
        $http = (new FakeHttpClient())
            ->expect('GET', '/indexes/geo', $this->index('geo', self::LIVE_CREATED))
            ->expect('GET', '/indexes/geo', self::NOT_FOUND, 404);
        $manager = $this->manager($http);

        $this->assertSame(self::LIVE_CREATED, $manager->fingerprint('geo'));
        $this->assertNull($manager->fingerprint('geo'));
    }

    public function testFingerprintRejectsAnIndexWithoutCreationTime(): void
    {
        $http = (new FakeHttpClient())->expect('GET', '/indexes/geo', ['uid' => 'geo', 'primaryKey' => 'id']);

        $this->expectException(MeilisearchException::class);
        $this->expectExceptionMessage('without its creation time');

        $this->manager($http)->fingerprint('geo');
    }

    public function testVersion(): void
    {
        $http = (new FakeHttpClient())->expect('GET', '/version', ['commitSha' => '47d0c34', 'commitDate' => 'unknown', 'pkgVersion' => '1.35.1']);

        $this->assertSame('1.35.1', $this->manager($http)->version());
    }

    public function testSnapshotHiddenFromTheKeyFails(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('POST', '/snapshots', 9, 'snapshotCreation', null)
            ->expect('GET', '/tasks/?uids=9&limit=1', self::NO_TASK);

        try {
            $this->manager($http)->snapshot();
            $this->fail('A snapshot that cannot be followed was accepted.');
        } catch (MeilisearchException $e) {
            $this->assertStringContainsString('"indexes": ["*"]', $e->getMessage());
            $this->assertInstanceOf(TaskNotFoundException::class, $e->getPrevious());
        }
    }

    public function testSnapshot(): void
    {
        $http = (new FakeHttpClient())
            ->expectTask('POST', '/snapshots', 9, 'snapshotCreation', null)
            ->expectTaskStatuses([9 => 'succeeded'], 'snapshotCreation', null);

        $this->manager($http)->snapshot();

        $this->assertSame(0, $http->remaining());
    }

    public function testRejectsAMalformedTask(): void
    {
        $http = (new FakeHttpClient())->expect('POST', '/snapshots', ['taskUid' => 'nine'], 202);

        $this->expectException(MeilisearchException::class);
        $this->expectExceptionMessage('Meilisearch returned a malformed task when trying to create a snapshot.');

        $this->manager($http)->snapshot();
    }

    public function testRejectsAnAnswerThatIsNotJson(): void
    {
        $http = (new FakeHttpClient())->expect('POST', '/snapshots', null, 502);

        $this->expectException(MeilisearchException::class);

        $this->manager($http)->snapshot();
    }

    private function manager(FakeHttpClient $http): MeilisearchIndexManager
    {
        return new MeilisearchIndexManager($http->meilisearch(), new IndexingOptions(pollInterval: 0.0));
    }

    /**
     * @return array<string, mixed>
     */
    private function index(string $uid, string $createdAt): array
    {
        return ['uid' => $uid, 'createdAt' => $createdAt, 'updatedAt' => $createdAt, 'primaryKey' => 'id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function task(int $uid, string $status): array
    {
        return ['uid' => $uid, 'indexUid' => 'geo', 'status' => $status, 'type' => 'documentAdditionOrUpdate'];
    }
}
