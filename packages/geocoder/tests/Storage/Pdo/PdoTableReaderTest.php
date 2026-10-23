<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;
use Tito10047\Geocoder\Storage\Pdo\PdoTableReader;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\StreetKey;
use Tito10047\Geocoder\Tests\Fake\Builds;

final class PdoTableReaderTest extends TestCase
{
    private \PDO $pdo;
    private TableNames $tables;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->tables = new TableNames();
        Builds::store($this->pdo, $this->tables);
    }

    public function testReadsTheHouseNumbersBackExactly(): void
    {
        $houseNumbers = iterator_to_array((new PdoTableReader($this->pdo))->houseNumbers($this->tables), false);

        $this->assertEquals([
            Builds::houseNumber(),
            new HouseNumber(new StreetKey(0xFFFFFFFFFFFFFFF), '7', null, new Coordinates(-1.5, 2.25)),
        ], $houseNumbers);
        $this->assertSame('14', $houseNumbers[0]->orientation);
    }

    public function testReadsTheTranslations(): void
    {
        $this->assertEquals(
            [new Translation('SK', NameKind::City, 'Košice', 'hu', 'Kassa')],
            iterator_to_array((new PdoTableReader($this->pdo))->translations($this->tables), false),
        );
    }

    public function testRejectsAnInvalidRow(): void
    {
        $this->pdo->exec("INSERT INTO geo_name (cc, kind, name, lang, translation) VALUES ('SK', 'street', 'a', 'b', 'c')");

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('A row of geo_name is not a translation');

        iterator_to_array((new PdoTableReader($this->pdo))->translations($this->tables));
    }

    public function testRejectsAnInvalidHouseNumber(): void
    {
        $this->pdo->exec("INSERT INTO geo_house (street_key, number, lat, lon) VALUES (1, ' ', 1, 1)");

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('A row of geo_house is not a house number');

        iterator_to_array((new PdoTableReader($this->pdo))->houseNumbers($this->tables));
    }

    public function testWrapsTheErrorsOfTheDatabase(): void
    {
        $this->expectException(StorageException::class);

        iterator_to_array((new PdoTableReader($this->pdo))->houseNumbers(new TableNames('missing')));
    }

    public function testRequiresAConnectionThatThrows(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);

        $this->expectException(InvalidArgumentException::class);

        new PdoTableReader($pdo);
    }
}
