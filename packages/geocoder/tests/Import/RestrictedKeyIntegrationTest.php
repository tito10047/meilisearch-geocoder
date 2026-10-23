<?php

namespace Tito10047\Geocoder\Tests\Import;

use Meilisearch\Client;
use Meilisearch\Http\Client as MeilisearchHttp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Import\Importer;
use Tito10047\Geocoder\Import\Photon\PhotonConverter;
use Tito10047\Geocoder\Index\Exception\MeilisearchException;
use Tito10047\Geocoder\Index\IndexingOptions;
use Tito10047\Geocoder\Index\IndexPublisher;
use Tito10047\Geocoder\Index\MeilisearchIndexManager;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Search\GeocoderFactory;
use Tito10047\Geocoder\Search\Suggestion;
use Tito10047\Geocoder\Status\GeocoderState;
use Tito10047\Geocoder\Status\GeocoderStatusProvider;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Tests\Fake\FailingPublishStorage;
use Tito10047\Geocoder\Tests\Fake\TemporaryDirectory;

/**
 * Imports, publishes, recovers and searches with API keys restricted to the indexes of the
 * geocoder ("indexes": ["<live uid>*"]), as on a Meilisearch shared with other applications.
 *
 * Needs the master key in MEILISEARCH_KEY (with MEILISEARCH_URL): it creates the restricted keys
 * and deletes them afterwards. A key restricted to some indexes does not see the tasks of no
 * index (the swaps), which the index manager confirms from the indexes instead.
 */
#[Group('integration')]
final class RestrictedKeyIntegrationTest extends TestCase
{
    private const DUMP = __DIR__.'/../Fixtures/photon/slovakia-sample.jsonl.zst';

    /**
     * The actions an index manager needs to import, publish, clean up and report the status:
     * documented in the bundle (docs/index.md, "API keys").
     */
    public const MANAGER_ACTIONS = [
        'indexes.create', 'indexes.get', 'indexes.delete', 'indexes.swap', 'indexes.compact',
        'settings.get', 'settings.update', 'documents.add', 'tasks.get', 'stats.get', 'version',
    ];

    /** The actions searching needs. */
    public const SEARCH_ACTIONS = ['search', 'documents.get'];

    /** @var non-empty-string */
    private string $url;
    private Client $master;
    private MeilisearchIndexManager $masterIndexes;
    private TimestampIndexNaming $naming;
    private TemporaryDirectory $temporary;
    /** @var list<string> */
    private array $keys = [];

    protected function setUp(): void
    {
        $url = getenv('MEILISEARCH_URL');
        $key = getenv('MEILISEARCH_KEY');
        if (!\is_string($url) || '' === $url || !\is_string($key) || '' === $key) {
            $this->markTestSkipped('Set MEILISEARCH_URL and MEILISEARCH_KEY (the master key) to a Meilisearch ≥ 1.18 to run the integration tests with restricted keys.');
        }
        $this->url = $url;
        $this->master = new Client($url, $key);
        $this->masterIndexes = $this->manager($key);
        $this->naming = new TimestampIndexNaming('it'.bin2hex(random_bytes(4)));
        $this->temporary = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        if (!isset($this->master)) {
            return;
        }
        foreach ($this->keys as $uid) {
            $this->master->deleteKey($uid);
        }
        foreach ($this->ownIndexes() as $uid) {
            $this->masterIndexes->delete($uid);
        }
        $this->temporary->remove();
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function managerKeys(): iterable
    {
        yield 'minimal actions' => [self::MANAGER_ACTIONS];
        yield 'every action' => [['*']];
    }

    /**
     * @param list<string> $actions
     */
    #[DataProvider('managerKeys')]
    public function testImportsPublishesAndReplacesTheIndex(array $actions): void
    {
        $indexes = $this->manager($this->key($actions));
        $pdo = new \PDO('sqlite:'.$this->temporary->path.'/geocoder.sqlite');
        $importer = new Importer(new PhotonConverter(), $indexes, new PdoBuildStorage($pdo), $this->naming);
        $live = $this->naming->liveUid();

        // the first publication renames the build
        $first = $importer->publish($importer->import([self::DUMP]));
        $this->assertSame([$live], $this->ownIndexes());
        $this->assertSame($first->indexUid, $importer->live()->indexUid);

        // the next one swaps it in and deletes the index it replaced
        sleep(1);
        $second = $importer->publish($importer->import([self::DUMP]));
        $this->assertSame([$live], $this->ownIndexes());
        $this->assertSame($second->indexUid, $importer->live()->indexUid);

        $status = (new GeocoderStatusProvider($indexes, $live, $importer))->status();
        $this->assertSame(GeocoderState::Ready, $status->state, (string) $status->error);
        $this->assertSame(21, $status->index?->documents);
        $this->assertNotNull($status->meilisearchVersion);
        $this->assertSame($second->indexUid, $status->liveBuild?->indexUid);

        $search = GeocoderFactory::create(new Client($this->url, $this->key(self::SEARCH_ACTIONS)), $pdo, $live);
        $this->assertInstanceOf(Suggestion::class, $search->geocode('Viničky'));
    }

    public function testRecoversFromAPublicationStoppedAfterTheSwap(): void
    {
        $indexes = $this->manager($this->key(self::MANAGER_ACTIONS));
        $pdo = new \PDO('sqlite:'.$this->temporary->path.'/geocoder.sqlite');
        $storage = new PdoBuildStorage($pdo);
        $failing = new FailingPublishStorage($storage);
        $importer = new Importer(new PhotonConverter(), $indexes, $failing, $this->naming);
        $live = $this->naming->liveUid();

        // the tables fail: the rename is undone
        $first = $importer->import([self::DUMP]);
        $failing->publishFailures = 1;
        try {
            $importer->publish($first);
            $this->fail('The publication did not fail.');
        } catch (StorageException) {
        }
        $this->assertSame([$first->indexUid], $this->ownIndexes());
        $importer->publish($importer->build($first->indexUid));
        $this->assertSame([$live], $this->ownIndexes());

        // the state left by a process stopped right after the swap: the index live, the tables not
        sleep(1);
        $second = $importer->import([self::DUMP]);
        $storage->recordPublication($second->tables, (string) $indexes->fingerprint($second->indexUid));
        (new IndexPublisher($indexes, $this->naming))->swapIn($second->indexUid);
        $this->assertSame($first->indexUid, $importer->live()->indexUid);

        $importer->publish($importer->build($second->indexUid));

        $this->assertSame($second->indexUid, $importer->live()->indexUid);
        $this->assertSame([$live], $this->ownIndexes());
        $this->assertSame($indexes->fingerprint($live), $storage->recordedPublication($importer->live()->tables));
    }

    public function testASnapshotNeedsAKeyForEveryIndex(): void
    {
        $indexes = $this->manager($this->key(['*']));

        $this->expectException(MeilisearchException::class);
        $this->expectExceptionMessage('"indexes": ["*"]');

        $indexes->snapshot();
    }

    /**
     * Creates a key allowed on the indexes of the test only.
     *
     * @param list<string> $actions
     */
    private function key(array $actions): string
    {
        $key = $this->master->createKey(['indexes' => [$this->naming->liveUid().'*'], 'actions' => $actions, 'expiresAt' => null]);
        $uid = $key->getUid();
        $secret = $key->getKey();
        if (null === $uid || null === $secret) {
            throw new \LogicException('Meilisearch created a key without its uid or its secret.');
        }
        $this->keys[] = $uid;

        return $secret;
    }

    private function manager(string $key): MeilisearchIndexManager
    {
        return new MeilisearchIndexManager(new MeilisearchHttp($this->url, $key), new IndexingOptions(batchSize: 10, taskTimeout: 60.0, pollInterval: 0.05));
    }

    /**
     * @return list<string>
     */
    private function ownIndexes(): array
    {
        $uids = array_values(array_filter($this->masterIndexes->indexUids(), fn (string $uid): bool => str_starts_with($uid, $this->naming->liveUid())));
        sort($uids);

        return $uids;
    }
}
