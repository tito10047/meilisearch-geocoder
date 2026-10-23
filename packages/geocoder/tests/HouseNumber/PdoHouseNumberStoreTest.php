<?php

namespace Tito10047\Geocoder\Tests\HouseNumber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\HouseNumber\PdoHouseNumberStore;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\StreetKey;

#[RequiresPhpExtension('pdo_sqlite')]
final class PdoHouseNumberStoreTest extends TestCase
{
    private const STREET = 909109604356383787;
    private const OTHER_STREET = 42;

    private \PDO $connection;

    protected function setUp(): void
    {
        $this->connection = new \PDO('sqlite::memory:');
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->createTable('geo_house');

        $rows = [
            [self::STREET, '12', null, '040 01', 48.1, 21.1],
            [self::STREET, '12', null, '040 01', 48.1001, 21.1001],
            [self::STREET, '120', null, null, 48.2, 21.2],
            [self::STREET, '12a', null, '040 01', 48.3, 21.3],
            [self::STREET, '12B', null, '040 01', 48.4, 21.4],
            [self::STREET, '9/12', '12', '040 01', 48.5, 21.5],
            [self::STREET, '12/18', '18', '040 01', 48.6, 21.6],
            [self::STREET, '606/42', '42', '040 01', 48.7, 21.7],
            [self::STREET, '24/420', '420', '040 01', 48.8, 21.8],
            [self::STREET, '1_0', null, null, 48.9, 21.9],
            [self::STREET, '1%', null, null, 49.0, 22.0],
            [self::STREET, '1!', null, null, 49.1, 22.1],
            [self::OTHER_STREET, '12', null, null, 47.0, 20.0],
        ];
        $insert = $this->connection->prepare('INSERT INTO geo_house (street_key, number, orientation, postcode, lat, lon) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($rows as $row) {
            $insert->execute($row);
        }
        $this->connection->exec("UPDATE geo_house SET suburb = 'Šaca', reference = '7101722639' WHERE number = '9/12'");
        $this->connection->exec("UPDATE geo_house SET suburb = '' WHERE number = '120'");
    }

    /**
     * @param positive-int $limit
     * @param list<string> $expected
     */
    #[DataProvider('provideLookups')]
    public function testFinds(string $number, int $limit, array $expected): void
    {
        $found = (new PdoHouseNumberStore($this->connection))->find(new StreetKey(self::STREET), $number, $limit);

        $this->assertSame($expected, array_map(static fn (HouseNumber $houseNumber): string => $houseNumber->number, $found));
    }

    /**
     * @return iterable<string, array{string, positive-int, list<string>}>
     */
    public static function provideLookups(): iterable
    {
        yield 'exact, orientation, conscription, then prefixes' => ['12', 10, ['12', '9/12', '12/18', '120', '12a', '12B']];
        yield 'limit' => ['12', 2, ['12', '9/12']];
        yield 'case-insensitive' => ['12b', 10, ['12B']];
        yield 'upper case typed' => ['12A', 10, ['12a']];
        yield 'orientation prefix' => ['42', 10, ['606/42', '24/420']];
        yield 'whole number with a slash' => ['606/42', 10, ['606/42']];
        yield 'nothing' => ['7', 10, []];
        yield 'underscore is literal' => ['1_', 10, ['1_0']];
        yield 'percent is literal' => ['1%', 10, ['1%']];
        yield 'escape character is literal' => ['1!', 10, ['1!']];
        yield 'whitespace trimmed' => [' 120 ', 10, ['120']];
    }

    public function testReadsTheHouseNumber(): void
    {
        $found = (new PdoHouseNumberStore($this->connection))->find(new StreetKey(self::STREET), '9/12', 1);

        $this->assertCount(1, $found);
        $this->assertSame(self::STREET, $found[0]->streetKey->value);
        $this->assertSame('9/12', $found[0]->number);
        $this->assertSame('12', $found[0]->orientation);
        $this->assertSame('040 01', $found[0]->postcode);
        $this->assertEqualsWithDelta(48.5, $found[0]->coordinates->latitude, \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(21.5, $found[0]->coordinates->longitude, \PHP_FLOAT_EPSILON);

        $this->assertSame('Šaca', $found[0]->suburb);
        $this->assertSame('7101722639', $found[0]->reference);

        $other = (new PdoHouseNumberStore($this->connection))->find(new StreetKey(self::STREET), '120', 1)[0];
        $this->assertNull($other->postcode);
        // an empty column is none
        $this->assertNull($other->suburb);
        $this->assertNull($other->reference);
    }

    public function testFindsByReference(): void
    {
        $store = new PdoHouseNumberStore($this->connection);

        $found = $store->findByReference('7101722639');

        $this->assertInstanceOf(HouseNumber::class, $found);
        $this->assertSame(self::STREET, $found->streetKey->value);
        $this->assertSame('9/12', $found->number);
        $this->assertSame('Šaca', $found->suburb);
        $this->assertSame('7101722639', $found->reference);
        $this->assertNull($store->findByReference('7101722638'));
        $this->assertNull($store->findByReference('710172263'));
    }

    public function testFindsAReferenceOfSeveralObjects(): void
    {
        $this->connection->exec("UPDATE geo_house SET reference = '42' WHERE number IN ('12a', '12B')");

        $this->assertContains((new PdoHouseNumberStore($this->connection))->findByReference('42')?->number, ['12a', '12B']);
    }

    public function testWrapsTheDatabaseErrorsOfTheReferences(): void
    {
        $this->expectException(GeocoderUnavailableException::class);

        (new PdoHouseNumberStore($this->connection, new TableNames(houseNumbers: 'missing_house')))->findByReference('1');
    }

    public function testReadsOnlyTheStreet(): void
    {
        $found = (new PdoHouseNumberStore($this->connection))->find(new StreetKey(self::OTHER_STREET), '1', 10);

        $this->assertSame(['12'], array_map(static fn (HouseNumber $houseNumber): string => $houseNumber->number, $found));
        $this->assertEqualsWithDelta(47.0, $found[0]->coordinates->latitude, \PHP_FLOAT_EPSILON);
    }

    public function testUsesTheTableNames(): void
    {
        $this->createTable('custom_house');
        $this->connection->exec("INSERT INTO custom_house VALUES (1, '7', NULL, NULL, 1.0, 2.0, NULL, NULL)");

        $found = (new PdoHouseNumberStore($this->connection, new TableNames(houseNumbers: 'custom_house')))->find(new StreetKey(1), '7', 5);

        $this->assertCount(1, $found);
    }

    public function testRejectsAZeroLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /* @phpstan-ignore argument.type */
        (new PdoHouseNumberStore($this->connection))->find(new StreetKey(self::STREET), '12', 0);
    }

    #[DataProvider('provideErrorModes')]
    public function testWrapsTheDatabaseErrors(int $errorMode): void
    {
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, $errorMode);

        $this->expectException(GeocoderUnavailableException::class);
        $this->expectExceptionMessage('missing_house');

        (new PdoHouseNumberStore($this->connection, new TableNames(houseNumbers: 'missing_house')))->find(new StreetKey(self::STREET), '12', 5);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideErrorModes(): iterable
    {
        yield 'exceptions' => [\PDO::ERRMODE_EXCEPTION];
        yield 'silent' => [\PDO::ERRMODE_SILENT];
    }

    public function testRejectsAnInvalidRow(): void
    {
        $this->connection->exec('INSERT INTO geo_house VALUES (7, \'1\', NULL, NULL, 91.0, 0.0, NULL, NULL)');

        $this->expectException(GeocoderUnavailableException::class);

        (new PdoHouseNumberStore($this->connection))->find(new StreetKey(7), '1', 5);
    }

    private function createTable(string $table): void
    {
        $this->connection->exec(<<<SQL
            CREATE TABLE {$table} (street_key BIGINT NOT NULL, number VARCHAR(32) NOT NULL, orientation VARCHAR(32) NULL, postcode VARCHAR(16) NULL, lat DOUBLE PRECISION NOT NULL, lon DOUBLE PRECISION NOT NULL, suburb VARCHAR(255) NULL, reference VARCHAR(32) NULL);
            CREATE INDEX {$table}_number ON {$table} (street_key, number);
            CREATE INDEX {$table}_orientation ON {$table} (street_key, orientation);
            CREATE INDEX {$table}_reference ON {$table} (reference);
            SQL);
    }
}
