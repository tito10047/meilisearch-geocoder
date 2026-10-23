<?php

namespace Tito10047\Geocoder\Tests\Name;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\PdoNameCatalog;
use Tito10047\Geocoder\Storage\TableNames;

#[RequiresPhpExtension('pdo_sqlite')]
final class PdoNameCatalogTest extends TestCase
{
    private \PDO $connection;

    protected function setUp(): void
    {
        $this->connection = new \PDO('sqlite::memory:');
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->createTable('geo_name');

        $insert = $this->connection->prepare('INSERT INTO geo_name (cc, kind, name, lang, translation) VALUES (?, ?, ?, ?, ?)');
        foreach ([
            ['AT', 'city', 'Wien', 'sk', 'Viedeň'],
            ['AT', 'city', 'Wien', 'hu', 'Bécs'],
            ['AT', 'country', 'AT', 'sk', 'Rakúsko'],
            ['SK', 'region', 'Košický kraj', 'en', 'Košice Region'],
            ['DE', 'city', 'Wien', 'sk', 'Wien (Nemecko)'],
        ] as $row) {
            $insert->execute($row);
        }
    }

    #[DataProvider('provideTranslations')]
    public function testTranslates(string $countryCode, NameKind $kind, string $name, string $language, ?string $expected): void
    {
        $this->assertSame($expected, (new PdoNameCatalog($this->connection))->translate($countryCode, $kind, $name, $language));
    }

    /**
     * @return iterable<string, array{string, NameKind, string, string, string|null}>
     */
    public static function provideTranslations(): iterable
    {
        yield 'city' => ['AT', NameKind::City, 'Wien', 'sk', 'Viedeň'];
        yield 'another language' => ['AT', NameKind::City, 'Wien', 'hu', 'Bécs'];
        yield 'country' => ['AT', NameKind::Country, 'AT', 'sk', 'Rakúsko'];
        yield 'region' => ['SK', NameKind::Region, 'Košický kraj', 'en', 'Košice Region'];
        yield 'other country' => ['DE', NameKind::City, 'Wien', 'sk', 'Wien (Nemecko)'];
        yield 'missing language' => ['AT', NameKind::City, 'Wien', 'pl', null];
        yield 'other kind' => ['AT', NameKind::Region, 'Wien', 'sk', null];
        yield 'unknown name' => ['AT', NameKind::City, 'Graz', 'sk', null];
    }

    public function testRemembersTheAnswers(): void
    {
        $catalog = new PdoNameCatalog($this->connection);
        $this->assertSame('Viedeň', $catalog->translate('AT', NameKind::City, 'Wien', 'sk'));
        $this->assertNull($catalog->translate('AT', NameKind::City, 'Graz', 'sk'));

        $this->connection->exec('DELETE FROM geo_name');

        $this->assertSame('Viedeň', $catalog->translate('AT', NameKind::City, 'Wien', 'sk'));
        $this->assertNull($catalog->translate('AT', NameKind::City, 'Graz', 'sk'));
    }

    public function testForgetsTheAnswersWhenTheMemoIsFull(): void
    {
        $catalog = new PdoNameCatalog($this->connection, memoSize: 1);
        $catalog->translate('AT', NameKind::City, 'Wien', 'sk');
        $this->connection->exec('DELETE FROM geo_name');

        $this->assertSame('Viedeň', $catalog->translate('AT', NameKind::City, 'Wien', 'sk'), 'Still remembered.');
        $this->assertNull($catalog->translate('AT', NameKind::City, 'Wien', 'hu'), 'The memo is full: forgotten and read again.');
        $this->assertNull($catalog->translate('AT', NameKind::City, 'Wien', 'sk'));
    }

    public function testRemembersNothingWithAZeroMemo(): void
    {
        $catalog = new PdoNameCatalog($this->connection, memoSize: 0);
        $catalog->translate('AT', NameKind::City, 'Wien', 'sk');
        $this->connection->exec('DELETE FROM geo_name');

        $this->assertNull($catalog->translate('AT', NameKind::City, 'Wien', 'sk'));
    }

    public function testUsesTheTableNames(): void
    {
        $this->createTable('custom_name');
        $this->connection->exec("INSERT INTO custom_name VALUES ('SK', 'city', 'Košice', 'hu', 'Kassa')");

        $catalog = new PdoNameCatalog($this->connection, new TableNames(translations: 'custom_name'));

        $this->assertSame('Kassa', $catalog->translate('SK', NameKind::City, 'Košice', 'hu'));
    }

    public function testRejectsANegativeMemoSize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PdoNameCatalog($this->connection, memoSize: -1);
    }

    #[DataProvider('provideErrorModes')]
    public function testWrapsTheDatabaseErrors(int $errorMode): void
    {
        $this->connection->setAttribute(\PDO::ATTR_ERRMODE, $errorMode);

        $this->expectException(GeocoderUnavailableException::class);
        $this->expectExceptionMessage('missing_name');

        (new PdoNameCatalog($this->connection, new TableNames(translations: 'missing_name')))->translate('AT', NameKind::City, 'Wien', 'sk');
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
}
