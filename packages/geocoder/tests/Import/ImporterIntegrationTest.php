<?php

namespace Tito10047\Geocoder\Tests\Import;

use Meilisearch\Client;
use Meilisearch\Http\Client as MeilisearchHttp;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Artifact\ArtifactBuilder;
use Tito10047\Geocoder\Artifact\ArtifactInstaller;
use Tito10047\Geocoder\Import\Importer;
use Tito10047\Geocoder\Import\Photon\PhotonConverter;
use Tito10047\Geocoder\Index\IndexingOptions;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\MeilisearchIndexManager;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Search\GeocoderFactory;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\Suggestion;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\TemporaryDirectory;

/**
 * Imports the sample dump into the Meilisearch at MEILISEARCH_URL (key MEILISEARCH_KEY),
 * publishes it and searches it; skipped when the variable is not set. Uses indexes of a random
 * live uid and deletes them afterwards.
 *
 * With MEILISEARCH_SNAPSHOT_DIR (the snapshot directory of that instance, as a local path) and
 * an instance holding no other index, it also builds an artifact and installs its tables.
 */
#[Group('integration')]
final class ImporterIntegrationTest extends TestCase
{
    private const DUMP = __DIR__.'/../Fixtures/photon/slovakia-sample.jsonl.zst';

    /** @var non-empty-string */
    private string $url;
    private string $key;
    private MeilisearchIndexManager $indexes;
    private TimestampIndexNaming $naming;
    private TemporaryDirectory $temporary;

    protected function setUp(): void
    {
        $url = getenv('MEILISEARCH_URL');
        if (!\is_string($url) || '' === $url) {
            $this->markTestSkipped('Set MEILISEARCH_URL (and MEILISEARCH_KEY) to a Meilisearch ≥ 1.18 to run the integration tests.');
        }
        $key = getenv('MEILISEARCH_KEY');
        $this->url = $url;
        $this->key = \is_string($key) ? $key : '';
        $this->indexes = new MeilisearchIndexManager(new MeilisearchHttp($this->url, $this->key), new IndexingOptions(batchSize: 10, taskTimeout: 120.0, pollInterval: 0.05));
        $this->naming = new TimestampIndexNaming('it'.bin2hex(random_bytes(4)));
        $this->temporary = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        if (!isset($this->indexes)) {
            return;
        }
        foreach ($this->indexes->indexUids() as $uid) {
            if (str_starts_with($uid, $this->naming->liveUid())) {
                $this->indexes->delete($uid);
            }
        }
        $this->temporary->remove();
    }

    public function testImportsPublishesAndSearchesTheSample(): void
    {
        $pdo = new \PDO('sqlite:'.$this->temporary->path.'/geocoder.sqlite');
        $importer = new Importer(new PhotonConverter(), $this->indexes, new PdoBuildStorage($pdo), $this->naming);

        $build = $importer->import([self::DUMP]);
        $this->assertSame(21, $this->indexes->status($build->indexUid)->documents);
        $this->assertSame((new IndexSettings())->hash(), $this->indexes->status($build->indexUid)->settingsHash);
        $this->assertFalse($this->indexes->exists($this->naming->liveUid()));

        $live = $importer->publish($build);

        $this->assertEquals($live, $importer->live());
        $this->assertFalse($this->indexes->exists($build->indexUid));
        $this->assertSearches($pdo);

        // a second import replaces the first one
        sleep(1);
        $second = $importer->publish($importer->import([self::DUMP]));
        $this->assertNotSame($build->indexUid, $second->indexUid);
        $this->assertSame([$this->naming->liveUid()], $this->ownIndexes());
        $this->assertSame([], (new PdoBuildStorage($pdo))->suffixes(new TableNames()));
        $this->assertSearches($pdo);

        $snapshotDirectory = getenv('MEILISEARCH_SNAPSHOT_DIR');
        if (!\is_string($snapshotDirectory) || '' === $snapshotDirectory || [$this->naming->liveUid()] !== $this->indexes->indexUids()) {
            return;
        }
        $artifact = (new ArtifactBuilder($this->indexes, $pdo, $snapshotDirectory, $this->naming))->build($second, $this->temporary->path.'/artifact');
        $this->assertGreaterThan(0, $artifact->manifest->snapshot->size);
        $this->assertSame(21, $artifact->manifest->documents);

        mkdir($this->temporary->path.'/meili_data');
        $target = new \PDO('sqlite::memory:');
        $installed = (new ArtifactInstaller($artifact->manifest->meilisearchVersion, $this->naming))->install($artifact->directory, $this->temporary->path.'/meili_data', $target);
        $this->assertFileExists($installed->snapshotPath);
        $this->assertEquals($second, $installed->build);
    }

    private function assertSearches(\PDO $pdo): void
    {
        $geocoder = GeocoderFactory::create(new Client($this->url, $this->key), $pdo, $this->naming->liveUid());

        $village = $geocoder->geocode('Viničky');
        $this->assertInstanceOf(Suggestion::class, $village);
        $this->assertSame('Viničky', $village->name);

        $addresses = array_filter($geocoder->suggest('Spodná 196/14 Viničky'), static fn (Suggestion $suggestion): bool => Kind::Address === $suggestion->kind);
        $this->assertNotSame([], $addresses);
        $address = array_values($addresses)[0];
        $this->assertSame('Spodná', $address->street);
        $this->assertSame('Viničky', $address->suburb);
        $this->assertSame('okres Trebišov', $address->county);
        $this->assertSame('30701583', $address->reference);

        // the official id, through the street document read by id
        $lookup = GeocoderFactory::createAddressLookup(new Client($this->url, $this->key), $pdo, $this->naming->liveUid());
        $found = $lookup->findByReference('30701583');
        $this->assertInstanceOf(Suggestion::class, $found);
        $this->assertSame($address->id, $found->id);
        $this->assertSame($address->label, $found->label);
        $this->assertSame('okres Trebišov', $found->county);
        $this->assertNull($lookup->findByReference('0'));
    }

    /**
     * @return list<string>
     */
    private function ownIndexes(): array
    {
        return array_values(array_filter($this->indexes->indexUids(), fn (string $uid): bool => str_starts_with($uid, $this->naming->liveUid())));
    }
}
