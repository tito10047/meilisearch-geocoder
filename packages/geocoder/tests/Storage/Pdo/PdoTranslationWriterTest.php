<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;
use Tito10047\Geocoder\Storage\Pdo\PdoSchema;
use Tito10047\Geocoder\Storage\Pdo\PdoTranslationWriter;
use Tito10047\Geocoder\Storage\TableNames;

final class PdoTranslationWriterTest extends TestCase
{
    private \PDO $pdo;
    private TableNames $tables;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->tables = new TableNames();
        (new PdoSchema($this->pdo))->createTables($this->tables);
    }

    public function testWritesEachTranslationOnce(): void
    {
        $writer = new PdoTranslationWriter($this->pdo, $this->tables, batchSize: 2);
        $writer->write(new Translation('SK', NameKind::City, 'Viničky', 'hu', 'Szőlőske'));
        $writer->write(new Translation('SK', NameKind::City, 'Viničky', 'hu', 'Szőlőske'));
        $writer->write(new Translation('SK', NameKind::Region, 'Košický kraj', 'hu', 'Kassai kerület'));
        // across batches, the first translation of a name wins
        $writer->write(new Translation('SK', NameKind::City, 'Viničky', 'hu', 'another'));
        $writer->write(new Translation('SK', NameKind::Country, 'SK', 'sk', 'Slovensko'));
        $writer->write(new Translation('HU', NameKind::City, 'Viničky', 'hu', 'Szőlőske'));
        $writer->close();
        $writer->close();

        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame([
            ['HU', 'city', 'Viničky', 'hu', 'Szőlőske'],
            ['SK', 'city', 'Viničky', 'hu', 'Szőlőske'],
            ['SK', 'country', 'SK', 'sk', 'Slovensko'],
            ['SK', 'region', 'Košický kraj', 'hu', 'Kassai kerület'],
        ], $this->rows());
    }

    public function testRespectsTheRowsAlreadyInTheTable(): void
    {
        $first = new PdoTranslationWriter($this->pdo, $this->tables);
        $first->write(new Translation('SK', NameKind::Country, 'SK', 'en', 'Slovakia'));
        $first->close();

        $second = new PdoTranslationWriter($this->pdo, $this->tables);
        $second->write(new Translation('SK', NameKind::Country, 'SK', 'en', 'Slovakia'));
        $second->write(new Translation('AT', NameKind::Country, 'AT', 'en', 'Austria'));
        $second->close();

        $this->assertCount(2, $this->rows());
    }

    public function testCannotWriteOnceClosed(): void
    {
        $writer = new PdoTranslationWriter($this->pdo, $this->tables);
        $writer->close();

        $this->expectException(StorageException::class);

        $writer->write(new Translation('SK', NameKind::Country, 'SK', 'en', 'Slovakia'));
    }

    public function testWrapsTheErrorsOfTheDatabase(): void
    {
        $writer = new PdoTranslationWriter($this->pdo, new TableNames(translations: 'missing'), 1);

        try {
            $writer->write(new Translation('SK', NameKind::Country, 'SK', 'en', 'Slovakia'));
            $this->fail('The translation was written.');
        } catch (StorageException $exception) {
            $this->assertInstanceOf(\PDOException::class, $exception->getPrevious());
        }
    }

    public function testRejectsAnInvalidBatchSize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PdoTranslationWriter($this->pdo, $this->tables, 0);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function rows(): array
    {
        $statement = $this->pdo->query('SELECT cc, kind, name, lang, translation FROM geo_name ORDER BY cc, kind, name, lang');
        $this->assertNotFalse($statement);

        return $statement->fetchAll(\PDO::FETCH_NUM);
    }
}
