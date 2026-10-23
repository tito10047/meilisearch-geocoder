<?php

namespace Tito10047\Geocoder\Tests\Artifact;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Artifact\ArtifactBuilder;
use Tito10047\Geocoder\Artifact\ArtifactManifest;
use Tito10047\Geocoder\Artifact\Exception\ArtifactException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\Pdo\PdoSchema;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\PublishedBuild;
use Tito10047\Geocoder\Tests\Fake\Sql;
use Tito10047\Geocoder\Tests\Fake\TemporaryDirectory;

final class ArtifactBuilderTest extends TestCase
{
    private TemporaryDirectory $temporary;

    protected function setUp(): void
    {
        $this->temporary = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->temporary->remove();
    }

    public function testBuildsAnArtifactOfTheLiveBuild(): void
    {
        $published = new PublishedBuild($this->temporary->path);

        $artifact = $published->artifact();

        $this->assertSame($this->temporary->path.'/artifact', $artifact->directory);
        $manifest = $artifact->manifest;
        $this->assertSame('1.35.1', $manifest->meilisearchVersion);
        $this->assertSame((new IndexSettings())->hash(), $manifest->settingsHash);
        $this->assertSame(PdoSchema::VERSION, $manifest->schemaVersion);
        $this->assertSame('geo_20261023120000', $manifest->indexUid);
        $this->assertSame('geo', $manifest->liveUid);
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:00:00 UTC'), $manifest->startedAt);
        $this->assertEquals(new \DateTimeImmutable('2026-10-23 14:00:00 UTC'), $manifest->createdAt);
        $this->assertSame(21, $manifest->documents);
        $this->assertEquals(new ImportCounts(4, 13, 4, 134), $manifest->counts);
        $this->assertEquals(['SK' => new \DateTimeImmutable('2026-10-17T23:02:06+00:00')], $manifest->dataTimestamps);

        $this->assertSame(['geocoder.sqlite', 'manifest.json', 'meilisearch.snapshot'], $this->files($artifact->directory));
        $this->assertEquals($manifest, ArtifactManifest::fromJson((string) file_get_contents($artifact->manifestPath())));
        $this->assertSame($manifest->snapshot->sha256, hash_file('sha256', $artifact->snapshotPath()));
        $this->assertSame($manifest->snapshot->size, filesize($artifact->snapshotPath()));
        $this->assertSame((string) file_get_contents($published->snapshotDirectory.'/data.ms.snapshot'), file_get_contents($artifact->snapshotPath()));
        $this->assertSame($manifest->database->sha256, hash_file('sha256', $artifact->databasePath()));
        $this->assertSame(['snapshot'], array_values(array_filter($published->indexes->log, static fn (string $entry): bool => 'snapshot' === $entry)));

        $database = new \PDO('sqlite:'.$artifact->databasePath());
        $this->assertEquals($published->build, (new PdoBuildStorage($database))->read(new TableNames()));
        $this->assertSame(134, (int) Sql::value($database, 'SELECT COUNT(*) FROM geo_house'));
    }

    public function testCopiesOnlyTheTablesOfTheGeocoderFromASharedDatabase(): void
    {
        $pdo = new \PDO('sqlite:'.$this->temporary->path.'/shared.sqlite');
        $pdo->exec('CREATE TABLE products (id INTEGER)');
        $published = new PublishedBuild($this->temporary->path, $pdo);

        $artifact = $published->artifact();

        $database = new \PDO('sqlite:'.$artifact->databasePath());
        $tables = Sql::column($database, "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name");
        $this->assertSame(['geo_house', 'geo_meta', 'geo_name'], $tables);
        $this->assertEquals($published->build, (new PdoBuildStorage($database))->read(new TableNames()));
        $this->assertSame(134, (int) Sql::value($database, 'SELECT COUNT(*) FROM geo_house'));
    }

    public function testCopiesLiveTablesOfOtherNamesUnderTheDefaultOnes(): void
    {
        $published = new PublishedBuild($this->temporary->path);
        $published->pdo->exec('ALTER TABLE geo_house RENAME TO my_house');
        $published->pdo->exec('ALTER TABLE geo_name RENAME TO my_name');
        $published->pdo->exec('ALTER TABLE geo_meta RENAME TO my_meta');
        $builder = new ArtifactBuilder($published->indexes, $published->pdo, $published->snapshotDirectory, tables: new TableNames('my_house', 'my_name', 'my_meta'));

        $artifact = $builder->build($published->build, $this->temporary->path.'/artifact');

        $database = new \PDO('sqlite:'.$artifact->databasePath());
        $this->assertSame(134, (int) Sql::value($database, 'SELECT COUNT(*) FROM geo_house'));
    }

    public function testRefusesABuildThatIsNotLive(): void
    {
        $published = new PublishedBuild($this->temporary->path);
        $published->clock->advance('+1 hour');
        $other = $published->importer->import([PublishedBuild::DUMP]);

        $this->expectException(ArtifactException::class);
        $this->expectExceptionMessage('The build geo_20261023130000 is not live (the live build is geo_20261023120000)');

        $published->artifactBuilder()->build($other, $this->temporary->path.'/artifact');
    }

    public function testRefusesAnInstanceWithOtherIndexes(): void
    {
        $published = new PublishedBuild($this->temporary->path);
        $published->indexes->indexes['products'] = 'products';

        $this->expectException(ArtifactException::class);
        $this->expectExceptionMessage('(products)');

        $published->artifact();
    }

    public function testRefusesAnIndexWithOtherSettings(): void
    {
        $published = new PublishedBuild($this->temporary->path);
        $published->indexes->settingsHashes['geo_20261023120000'] = (new IndexSettings(maxTotalHits: 50))->hash();

        $this->expectException(ArtifactException::class);
        $this->expectExceptionMessage('settings');

        $published->artifact();
    }

    public function testRefusesAMissingSnapshotDirectory(): void
    {
        $published = new PublishedBuild($this->temporary->path);

        $this->expectException(ArtifactException::class);
        $this->expectExceptionMessage('does not exist');

        (new ArtifactBuilder($published->indexes, $published->pdo, $this->temporary->path.'/missing'))->build($published->build, $this->temporary->path.'/artifact');
    }

    public function testFailsWhenNoSnapshotAppearsAndRemovesWhatItWrote(): void
    {
        $published = new PublishedBuild($this->temporary->path);
        $published->indexes->snapshotDirectory = null;
        touch($published->snapshotDirectory.'/data.ms.snapshot', time() - 3600);

        try {
            $published->artifact();
            $this->fail('An artifact was built without a snapshot.');
        } catch (ArtifactException $exception) {
            $this->assertStringContainsString('no new snapshot appeared', $exception->getMessage());
        }

        $this->assertDirectoryDoesNotExist($this->temporary->path.'/artifact');
    }

    public function testPicksTheSnapshotItCreated(): void
    {
        $published = new PublishedBuild($this->temporary->path);
        file_put_contents($published->snapshotDirectory.'/data.ms.snapshot', 'the previous snapshot');
        touch($published->snapshotDirectory.'/data.ms.snapshot', time() - 3600);
        file_put_contents($published->snapshotDirectory.'/other.snapshot', 'another one');
        touch($published->snapshotDirectory.'/other.snapshot', time() - 60);

        $artifact = $published->artifact();

        $this->assertStringStartsWith('{', (string) file_get_contents($artifact->snapshotPath()));
    }

    public function testRefusesADirectoryThatIsNotEmpty(): void
    {
        $published = new PublishedBuild($this->temporary->path);

        $this->expectException(InvalidArgumentException::class);

        $published->artifactBuilder()->build($published->build, $this->temporary->path);
    }

    public function testRequiresAConnectionThatThrows(): void
    {
        $published = new PublishedBuild($this->temporary->path);
        $published->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        $this->expectException(InvalidArgumentException::class);

        $published->artifactBuilder();
    }

    /**
     * @return list<string>
     */
    private function files(string $directory): array
    {
        return array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
    }
}
