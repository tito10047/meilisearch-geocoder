<?php

namespace Tito10047\Geocoder\Tests\Name;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\PdoCountryNameSource;
use Tito10047\Geocoder\Name\Translation;
use Tito10047\Geocoder\Storage\TableNames;

#[RequiresPhpExtension('pdo_sqlite')]
final class PdoCountryNameSourceTest extends TestCase
{
    private \PDO $connection;

    protected function setUp(): void
    {
        $this->connection = new \PDO('sqlite::memory:');
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->createTable('geo_name');
    }

    public function testListsTheNamesOfTheImportedCountries(): void
    {
        $this->insert('geo_name', [
            ['SK', 'country', 'SK', 'sk', 'Slovensko'],
            ['SK', 'country', 'SK', 'en', 'Slovakia'],
            ['SK', 'country', 'SK', 'und', 'Slovensko'],
            ['SK', 'city', 'Košice', 'hu', 'Kassa'],
            ['AT', 'country', 'AT', 'sk', 'Rakúsko'],
            ['AT', 'city', 'Wien', 'sk', 'Viedeň'],
            // no town of France was imported
            ['FR', 'country', 'FR', 'sk', 'Francúzsko'],
            // a region does not make a country imported
            ['HU', 'country', 'HU', 'sk', 'Maďarsko'],
            ['HU', 'region', 'Pest', 'sk', 'Pešť'],
        ]);

        $this->assertEquals([
            new Translation('AT', NameKind::Country, 'AT', 'sk', 'Rakúsko'),
            new Translation('SK', NameKind::Country, 'SK', 'en', 'Slovakia'),
            new Translation('SK', NameKind::Country, 'SK', 'sk', 'Slovensko'),
            new Translation('SK', NameKind::Country, 'SK', 'und', 'Slovensko'),
        ], (new PdoCountryNameSource($this->connection))->countryNames());
    }

    public function testSkipsTheRowsNamingNoCountry(): void
    {
        $this->insert('geo_name', [
            ['SK', 'city', 'Košice', 'hu', 'Kassa'],
            ['SK', 'country', 'SK', 'sk', ''],
            ['SK', 'country', 'SK', 'SK', 'Slovensko'],
            ['SK', 'country', 'SK', 'en', ' Slovakia '],
        ]);

        $this->assertEquals([new Translation('SK', NameKind::Country, 'SK', 'en', 'Slovakia')], (new PdoCountryNameSource($this->connection))->countryNames());
    }

    public function testReadsAnotherTable(): void
    {
        $this->createTable('custom_name');
        $this->insert('custom_name', [['SK', 'country', 'SK', 'sk', 'Slovensko'], ['SK', 'city', 'Košice', 'hu', 'Kassa']]);

        $this->assertCount(1, (new PdoCountryNameSource($this->connection, new TableNames(translations: 'custom_name')))->countryNames());
    }

    #[DataProvider('provideErrorModes')]
    public function testWrapsTheErrorsOfTheDatabase(int $errorMode): void
    {
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, $errorMode);

        $this->expectException(GeocoderUnavailableException::class);
        $this->expectExceptionMessage('missing_name');

        (new PdoCountryNameSource($this->connection, new TableNames(translations: 'missing_name')))->countryNames();
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideErrorModes(): iterable
    {
        yield 'exceptions' => [\PDO::ERRMODE_EXCEPTION];
        yield 'silent' => [\PDO::ERRMODE_SILENT];
    }

    private function createTable(string $table): void
    {
        $this->connection->exec("CREATE TABLE {$table} (cc CHAR(2) NOT NULL, kind VARCHAR(8) NOT NULL, name VARCHAR(255) NOT NULL, lang VARCHAR(3) NOT NULL, translation VARCHAR(255) NOT NULL, PRIMARY KEY (cc, kind, name, lang))");
    }

    /**
     * @param list<list<string>> $rows
     */
    private function insert(string $table, array $rows): void
    {
        $insert = $this->connection->prepare("INSERT INTO {$table} (cc, kind, name, lang, translation) VALUES (?, ?, ?, ?, ?)");
        foreach ($rows as $row) {
            $insert->execute($row);
        }
    }
}
