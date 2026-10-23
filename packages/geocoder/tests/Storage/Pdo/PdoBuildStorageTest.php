<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\Pdo\PdoSchema;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\Builds;
use Tito10047\Geocoder\Tests\Fake\Sql;

final class PdoBuildStorageTest extends TestCase
{
    private \PDO $pdo;
    private PdoBuildStorage $storage;
    private TableNames $live;
    private TableNames $build;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->storage = new PdoBuildStorage($this->pdo);
        $this->live = new TableNames();
        $this->build = $this->live->withSuffix('_20261023120000');
    }

    public function testCreatesFillsCompletesAndReadsABuild(): void
    {
        $this->assertFalse($this->storage->exists($this->build));
        $this->assertSame(PdoSchema::VERSION, $this->storage->schemaVersion());

        $build = Builds::store($this->pdo, $this->build);

        $this->assertTrue($this->storage->exists($this->build));
        $this->assertSame('2', $this->value('SELECT COUNT(*) FROM geo_house_20261023120000'));
        $this->assertSame('1', $this->value('SELECT COUNT(*) FROM geo_name_20261023120000'));
        $this->assertContains('geo_house_20261023120000_number', $this->objects('index'));
        $this->assertEquals($build, $this->storage->read($this->build));
    }

    public function testRefusesToCreateTablesThatExist(): void
    {
        $this->pdo->exec('CREATE TABLE geo_name_20261023120000 (x INTEGER)');

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('exists already');

        $this->storage->create($this->build);
    }

    public function testReadsNoBuildWithoutTheMetadataTable(): void
    {
        $this->expectException(BuildNotFoundException::class);

        $this->storage->read($this->build);
    }

    public function testReadsNoBuildFromAnEmptyMetadataTable(): void
    {
        $this->storage->create($this->build);

        $this->expectException(BuildNotFoundException::class);

        $this->storage->read($this->build);
    }

    public function testPublishesTheFirstBuildByRenamingIt(): void
    {
        $build = Builds::store($this->pdo, $this->build);

        $this->storage->publish($this->build, $this->live);

        $this->assertSame(['geo_house', 'geo_meta', 'geo_name'], $this->objects('table'));
        $this->assertEquals($build->withTables($this->live), $this->storage->read($this->live));
    }

    public function testReplacesTheLiveTables(): void
    {
        Builds::store($this->pdo, $this->live->withSuffix('_20261022000000'), 'geo_20261022000000');
        $this->storage->publish($this->live->withSuffix('_20261022000000'), $this->live);
        $build = Builds::store($this->pdo, $this->build);

        $this->storage->publish($this->build, $this->live);

        $this->assertSame(['geo_house', 'geo_meta', 'geo_name'], $this->objects('table'));
        $this->assertSame('geo_20261023120000', $this->storage->read($this->live)->indexUid);
        $this->assertEquals($build->withTables($this->live), $this->storage->read($this->live));
        // the indexes of the replaced tables are gone with them
        $this->assertSame(['geo_house_20261023120000_number', 'geo_house_20261023120000_orientation'], $this->objects('index'));
    }

    public function testRefusesToPublishAnIncompleteBuild(): void
    {
        Builds::store($this->pdo, $this->build);
        $this->pdo->exec('DROP TABLE geo_meta_20261023120000');

        try {
            $this->storage->publish($this->build, $this->live);
            $this->fail('An incomplete build was published.');
        } catch (BuildNotFoundException $exception) {
            $this->assertStringContainsString('geo_meta_20261023120000', $exception->getMessage());
        }
        $this->assertFalse($this->storage->exists($this->live));
    }

    public function testRollsThePublicationBackWhenAStatementFails(): void
    {
        Builds::store($this->pdo, $this->build);
        // a view of the same name cannot be dropped as a table: the publication fails after its first rename
        $this->pdo->exec('CREATE VIEW geo_meta AS SELECT 1');

        try {
            $this->storage->publish($this->build, $this->live);
            $this->fail('The publication did not fail.');
        } catch (StorageException) {
        }

        $this->assertFalse($this->pdo->inTransaction());
        $this->assertTrue($this->storage->exists($this->build));
        $this->assertSame(['geo_house_20261023120000', 'geo_meta_20261023120000', 'geo_name_20261023120000'], $this->objects('table'));
    }

    public function testDropsTheTables(): void
    {
        Builds::store($this->pdo, $this->build);

        $this->storage->drop($this->build);
        $this->storage->drop($this->build);

        $this->assertFalse($this->storage->exists($this->build));
    }

    public function testListsTheSuffixesOfTheTables(): void
    {
        Builds::store($this->pdo, $this->build);
        $this->storage->create($this->live->withSuffix('_x'));
        $this->storage->create($this->live);
        $this->pdo->exec('CREATE TABLE products (id INTEGER)');

        $this->assertSame(['_20261023120000', '_x'], $this->storage->suffixes($this->live));
    }

    public function testRollsBackAnOpenTransaction(): void
    {
        // a batch of one row: inserted at once, in a transaction left open until more rows come
        $storage = new PdoBuildStorage($this->pdo, houseNumberBatchSize: 1);
        $storage->create($this->build);
        $storage->houseNumberWriter($this->build)->write(Builds::houseNumber());
        $this->assertTrue($this->pdo->inTransaction());

        $storage->rollBack();
        $storage->rollBack();

        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame('0', $this->value('SELECT COUNT(*) FROM geo_house_20261023120000'));
    }

    public function testRejectsInvalidSizes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PdoBuildStorage($this->pdo, houseNumberBatchSize: 0);
    }

    private function value(string $sql): string
    {
        return Sql::value($this->pdo, $sql);
    }

    /**
     * @return list<string>
     */
    private function objects(string $type): array
    {
        $statement = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = ? AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $statement->execute([$type]);

        return array_values(array_map(strval(...), $statement->fetchAll(\PDO::FETCH_COLUMN)));
    }
}
