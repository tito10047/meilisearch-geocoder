<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\DumpSummary;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildMetadata;
use Tito10047\Geocoder\Storage\Pdo\PdoSchema;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\Builds;

final class PdoBuildMetadataTest extends TestCase
{
    private \PDO $pdo;
    private TableNames $tables;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->tables = new TableNames();
        (new PdoSchema($this->pdo))->createTables($this->tables);
    }

    public function testWritesTheBuildAndItsSummaries(): void
    {
        (new PdoBuildMetadata($this->pdo))->write(Builds::build($this->tables));

        $this->assertSame([
            'count.house_numbers' => '2',
            'count.places' => '11',
            'count.pois' => '6',
            'count.streets' => '22',
            'data_timestamp.AT' => '2026-10-16T08:00:00+00:00',
            'data_timestamp.SK' => '2026-10-16T08:00:00+00:00',
            'dumps' => '[{"dump":"slovakia.jsonl.zst","data_timestamp":"2026-10-17T23:02:06+00:00","countries":["SK"],"counts":{"places":10,"streets":20,"pois":3,"house_numbers":2}},{"dump":"austria.jsonl.zst","data_timestamp":"2026-10-16T08:00:00+00:00","countries":["AT","SK"],"counts":{"places":1,"streets":2,"pois":3,"house_numbers":0}}]',
            'index_uid' => 'geo_20261023120000',
            'schema_version' => '2',
            'settings_hash' => Builds::build($this->tables)->settingsHash,
            'started_at' => '2026-10-23T12:00:00+00:00',
        ], $this->rows());
    }

    public function testWritesTheFileNameOfADumpGivenByItsPath(): void
    {
        $build = Builds::build($this->tables);
        $dump = $build->dumps[0];
        $build = new Build($build->indexUid, $build->tables, $build->startedAt, $build->settingsHash, $build->schemaVersion, [
            new DumpSummary('/srv/build-machine/dumps/slovakia.jsonl.zst', $dump->dataTimestamp, $dump->countryCodes, $dump->counts),
        ]);
        $metadata = new PdoBuildMetadata($this->pdo);

        $metadata->write($build);

        $this->assertSame('slovakia.jsonl.zst', $metadata->read($this->tables)->dumps[0]->dump);
        $this->assertStringNotContainsString('build-machine', $this->rows()['dumps']);
    }

    public function testReplacesTheMetadataAndReadsItBack(): void
    {
        $metadata = new PdoBuildMetadata($this->pdo);
        $metadata->write(Builds::build($this->tables, 'geo_20261022000000'));
        $build = Builds::build($this->tables);

        $metadata->write($build);

        $this->assertEquals($build, $metadata->read($this->tables));
    }

    public function testRecordsThePublicationOnceAndKeepsTheBuildReadable(): void
    {
        $metadata = new PdoBuildMetadata($this->pdo);
        $build = Builds::build($this->tables);
        $metadata->write($build);
        $this->assertNull($metadata->readPublication($this->tables));

        $metadata->writePublication($this->tables, '2026-10-23T12:00:00.1Z');
        $metadata->writePublication($this->tables, '2026-10-23T12:00:00.2Z');

        $this->assertSame('2026-10-23T12:00:00.2Z', $metadata->readPublication($this->tables));
        $this->assertSame('2026-10-23T12:00:00.2Z', $this->rows()['publication.index_fingerprint']);
        $this->assertEquals($build, $metadata->read($this->tables));
    }

    public function testRecordsNoPublicationWithoutABuild(): void
    {
        $metadata = new PdoBuildMetadata($this->pdo);

        try {
            $metadata->writePublication($this->tables, 'x');
            $this->fail('A publication was recorded without a build.');
        } catch (BuildNotFoundException) {
            $this->assertSame([], $this->rows());
        }

        $this->expectException(BuildNotFoundException::class);

        $metadata->readPublication($this->tables);
    }

    public function testRejectsAnEmptyFingerprint(): void
    {
        $metadata = new PdoBuildMetadata($this->pdo);
        $metadata->write(Builds::build($this->tables));

        $this->expectException(InvalidArgumentException::class);

        $metadata->writePublication($this->tables, '');
    }

    public function testFailsToRecordThePublicationInAMissingTable(): void
    {
        $this->expectException(StorageException::class);

        (new PdoBuildMetadata($this->pdo))->readPublication(new TableNames(metadata: 'missing_meta'));
    }

    public function testReadsNoBuildFromAnEmptyTable(): void
    {
        $this->expectException(BuildNotFoundException::class);

        (new PdoBuildMetadata($this->pdo))->read($this->tables);
    }

    #[DataProvider('invalidMetadata')]
    public function testRejectsInvalidMetadata(string $key, string $value): void
    {
        $metadata = new PdoBuildMetadata($this->pdo);
        $metadata->write(Builds::build($this->tables));
        $this->pdo->prepare('UPDATE geo_meta SET meta_value = ? WHERE meta_key = ?')->execute([$value, $key]);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('The metadata of the build in geo_meta is invalid');

        $metadata->read($this->tables);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidMetadata(): iterable
    {
        yield 'dumps not JSON' => ['dumps', '[{'];
        yield 'dumps not a list' => ['dumps', '1'];
        yield 'no dump' => ['dumps', '[]'];
        yield 'a dump without counts' => ['dumps', '[{"dump":"a","data_timestamp":"2026-10-17T23:02:06+00:00","countries":["SK"]}]'];
        yield 'a count not an integer' => ['dumps', '[{"dump":"a","data_timestamp":"2026-10-17T23:02:06+00:00","countries":["SK"],"counts":{"places":"1","streets":0,"pois":0,"house_numbers":0}}]'];
        yield 'a country not a string' => ['dumps', '[{"dump":"a","data_timestamp":"2026-10-17T23:02:06+00:00","countries":[1],"counts":{"places":1,"streets":0,"pois":0,"house_numbers":0}}]'];
        yield 'a timestamp' => ['started_at', 'yesterday'];
        yield 'the settings hash' => ['settings_hash', 'abc'];
        yield 'the schema version' => ['schema_version', 'x'];
    }

    public function testWrapsTheErrorsOfTheDatabase(): void
    {
        $this->expectException(StorageException::class);

        (new PdoBuildMetadata($this->pdo))->write(Builds::build(new TableNames('a', 'b', 'missing')));
    }

    public function testWrapsTheErrorsOfTheDatabaseWhenReading(): void
    {
        $this->expectException(StorageException::class);

        (new PdoBuildMetadata($this->pdo))->read(new TableNames('a', 'b', 'missing'));
    }

    /**
     * @return array<string, string>
     */
    private function rows(): array
    {
        $statement = $this->pdo->query('SELECT meta_key, meta_value FROM geo_meta ORDER BY meta_key');
        $this->assertNotFalse($statement);

        return $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
    }
}
