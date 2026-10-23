<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Storage\Pdo\PdoSchema;
use Tito10047\Geocoder\Storage\TableNames;

final class PdoSchemaTest extends TestCase
{
    private \PDO $pdo;
    private TableNames $tables;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->tables = (new TableNames())->withSuffix('_build');
    }

    public function testCreatesTheTablesThenTheIndexes(): void
    {
        $schema = new PdoSchema($this->pdo);

        $schema->createTables($this->tables);
        $this->assertSame(['geo_house_build', 'geo_meta_build', 'geo_name_build'], $this->objects('table'));
        $this->assertSame(['street_key', 'number', 'orientation', 'postcode', 'lat', 'lon'], $this->columns('geo_house_build'));
        $this->assertSame(['cc', 'kind', 'name', 'lang', 'translation'], $this->columns('geo_name_build'));
        $this->assertSame(['meta_key', 'meta_value'], $this->columns('geo_meta_build'));
        $this->assertNotContains('geo_house_build_number', $this->objects('index'));

        $schema->createIndexes($this->tables);
        $indexes = $this->objects('index');
        $this->assertContains('geo_house_build_number', $indexes);
        $this->assertContains('geo_house_build_orientation', $indexes);
    }

    public function testTheTranslationsHaveAPrimaryKey(): void
    {
        (new PdoSchema($this->pdo))->createTables($this->tables);
        $insert = "INSERT INTO geo_name_build (cc, kind, name, lang, translation) VALUES ('SK', 'city', 'Viničky', 'hu', 'Szőlőske')";
        $this->pdo->exec($insert);

        $this->expectException(\PDOException::class);

        $this->pdo->exec($insert);
    }

    public function testDropsTheTables(): void
    {
        $schema = new PdoSchema($this->pdo);
        $schema->createTables($this->tables);
        $schema->createIndexes($this->tables);

        $schema->dropTables($this->tables);
        $schema->dropTables($this->tables);

        $this->assertSame([], $this->objects('table'));
        $this->assertSame([], $this->objects('index'));
    }

    public function testWrapsTheErrorsOfTheDatabase(): void
    {
        $schema = new PdoSchema($this->pdo);
        $schema->createTables($this->tables);

        try {
            $schema->createTables($this->tables);
            $this->fail('The tables were created twice.');
        } catch (StorageException $exception) {
            $this->assertInstanceOf(\PDOException::class, $exception->getPrevious());
            $this->assertStringContainsString('CREATE TABLE geo_house_build', $exception->getMessage());
        }
    }

    public function testRequiresAConnectionThatThrows(): void
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        $this->expectException(InvalidArgumentException::class);

        new PdoSchema($this->pdo);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function objects(string $type): array
    {
        $statement = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = ? AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $statement->execute([$type]);

        return $statement->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function columns(string $table): array
    {
        $statement = $this->pdo->query('PRAGMA table_info('.$table.')');
        $this->assertNotFalse($statement);

        return $statement->fetchAll(\PDO::FETCH_COLUMN, 1);
    }
}
