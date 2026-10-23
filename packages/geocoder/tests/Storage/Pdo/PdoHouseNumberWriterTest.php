<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\Storage\Pdo\PdoHouseNumberWriter;
use Tito10047\Geocoder\Storage\Pdo\PdoSchema;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\StreetKey;

final class PdoHouseNumberWriterTest extends TestCase
{
    private \PDO $pdo;
    private TableNames $tables;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->tables = new TableNames();
        (new PdoSchema($this->pdo))->createTables($this->tables);
    }

    #[DataProvider('provideSizes')]
    public function testWritesTheHouseNumbers(int $count, int $batchSize, int $transactionSize): void
    {
        $writer = new PdoHouseNumberWriter($this->pdo, $this->tables, $batchSize, $transactionSize);
        for ($i = 1; $i <= $count; ++$i) {
            $writer->write(new HouseNumber(new StreetKey($i), $i.'/'.($i + 1), 0 === $i % 2 ? '076 31' : null, new Coordinates(48.3981499, 21.7475491 + $i / 1e7)));
        }
        $writer->close();
        $writer->close();

        $this->assertFalse($this->pdo->inTransaction());
        $rows = $this->rows();
        $this->assertCount($count, $rows);
        $this->assertSame([1, '1/2', '2', null, 48.3981499, 21.7475492], $rows[0]);
        if ($count > 1) {
            $this->assertSame([2, '2/3', '3', '076 31', 48.3981499, 21.7475493], $rows[1]);
        }
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function provideSizes(): iterable
    {
        yield 'one' => [1, 500, 50_000];
        yield 'less than a batch' => [7, 10, 100];
        yield 'exactly a batch' => [10, 10, 100];
        yield 'batches and a rest' => [25, 10, 100];
        yield 'several transactions' => [25, 3, 7];
    }

    public function testKeepsTheExactCoordinatesAndTheLargestKeys(): void
    {
        $writer = new PdoHouseNumberWriter($this->pdo, $this->tables);
        $writer->write(new HouseNumber(new StreetKey(0xFFFFFFFFFFFFFFF), '12a', null, new Coordinates(48.123456789012345, -179.98765432109876)));
        $writer->close();

        $this->assertSame([[0xFFFFFFFFFFFFFFF, '12a', null, null, 48.123456789012345, -179.98765432109876]], $this->rows());
    }

    public function testWritesNothing(): void
    {
        (new PdoHouseNumberWriter($this->pdo, $this->tables))->close();

        $this->assertSame([], $this->rows());
    }

    public function testLeavesTheTransactionOfTheCallerOpen(): void
    {
        $this->pdo->beginTransaction();
        $writer = new PdoHouseNumberWriter($this->pdo, $this->tables, 1, 1);
        $writer->write(new HouseNumber(new StreetKey(1), '1', null, new Coordinates(48.0, 21.0)));
        $writer->write(new HouseNumber(new StreetKey(1), '2', null, new Coordinates(48.0, 21.0)));
        $writer->close();

        $this->assertTrue($this->pdo->inTransaction());
        $this->pdo->rollBack();
        $this->assertSame([], $this->rows());
    }

    public function testCannotWriteOnceClosed(): void
    {
        $writer = new PdoHouseNumberWriter($this->pdo, $this->tables);
        $writer->close();

        $this->expectException(StorageException::class);

        $writer->write(new HouseNumber(new StreetKey(1), '1', null, new Coordinates(48.0, 21.0)));
    }

    public function testWrapsTheErrorsOfTheDatabase(): void
    {
        $writer = new PdoHouseNumberWriter($this->pdo, new TableNames(houseNumbers: 'missing'), 1);

        try {
            $writer->write(new HouseNumber(new StreetKey(1), '1', null, new Coordinates(48.0, 21.0)));
            $this->fail('The house number was written.');
        } catch (StorageException $exception) {
            $this->assertInstanceOf(\PDOException::class, $exception->getPrevious());
        }
        $this->assertFalse($this->pdo->inTransaction());
    }

    public function testRejectsInvalidSizes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PdoHouseNumberWriter($this->pdo, $this->tables, 0);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function rows(): array
    {
        $statement = $this->pdo->query('SELECT street_key, number, orientation, postcode, lat, lon FROM geo_house ORDER BY street_key, number');
        $this->assertNotFalse($statement);

        return $statement->fetchAll(\PDO::FETCH_NUM);
    }
}
