<?php

namespace Tito10047\Geocoder\Tests\Import\File;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\DumpSummary;
use Tito10047\Geocoder\Import\File\FileExportConverter;
use Tito10047\Geocoder\Import\File\FileExporter;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Import\Importer;
use Tito10047\Geocoder\Import\Photon\PhotonConverter;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\FrozenClock;
use Tito10047\Geocoder\Tests\Fake\InMemoryDocumentWriter;
use Tito10047\Geocoder\Tests\Fake\InMemoryHouseNumberWriter;
use Tito10047\Geocoder\Tests\Fake\InMemoryTranslationWriter;
use Tito10047\Geocoder\Tests\Fake\RecordingProgress;
use Tito10047\Geocoder\Tests\Fake\Sql;
use Tito10047\Geocoder\Tests\Fake\TemporaryDirectory;
use Tito10047\Geocoder\Tests\Index\Fixtures\InMemoryIndexManager;

final class FileExportConverterTest extends TestCase
{
    private const DUMP = __DIR__.'/../../Fixtures/photon/slovakia-sample.jsonl';

    private TemporaryDirectory $temporary;
    private string $export;

    protected function setUp(): void
    {
        $this->temporary = new TemporaryDirectory();
        $this->export = $this->temporary->path.'/export';
        (new FileExporter(new PhotonConverter(), clock: new FrozenClock(new \DateTimeImmutable('2026-10-23 12:00:00 UTC'))))->export([self::DUMP], $this->export);
    }

    protected function tearDown(): void
    {
        $this->temporary->remove();
    }

    /**
     * The export gives what the dump gives.
     */
    public function testReadsAnExportLikeTheDumps(): void
    {
        $fromDump = [new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter()];
        (new PhotonConverter())->convert(self::DUMP, ...$fromDump);
        $fromExport = [new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter()];
        $progress = new RecordingProgress();

        $summaries = (new FileExportConverter(progressInterval: 10))->convert($this->export, ...[...$fromExport, $progress]);

        $this->assertEquals($fromDump[0]->documents, $fromExport[0]->documents);
        $this->assertEquals($fromDump[1]->houseNumbers, $fromExport[1]->houseNumbers);
        $this->assertEquals($fromDump[2]->translations, $fromExport[2]->translations);
        $this->assertEquals([new DumpSummary('slovakia-sample.jsonl', new \DateTimeImmutable('2026-10-17T23:02:06+00:00'), ['SK'], new ImportCounts(4, 13, 4, 134))], $summaries);
        $this->assertGreaterThan(5, \count($progress->calls));
        $this->assertEquals([$this->export, new ImportCounts(4, 13, 4, 134)], $progress->calls[\count($progress->calls) - 1]);
        $this->assertFalse($fromExport[0]->closed);
    }

    /**
     * The manifest of the export: the summary of each original dump, not one for the export.
     */
    public function testReturnsTheSummaryOfEachDumpOfTheExport(): void
    {
        $austria = $this->olderDump();
        $export = $this->temporary->path.'/two';
        (new FileExporter(new PhotonConverter()))->export([self::DUMP, $austria], $export);

        $summaries = (new FileExportConverter())->convert($export, new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter());

        $this->assertEquals([
            new DumpSummary('slovakia-sample.jsonl', new \DateTimeImmutable('2026-10-17T23:02:06+00:00'), ['SK'], new ImportCounts(4, 13, 4, 134)),
            new DumpSummary('austria-sample.jsonl', new \DateTimeImmutable('2026-10-10T20:00:00+00:00'), ['SK'], new ImportCounts(4, 13, 4, 134)),
        ], $summaries);
    }

    /**
     * Publishing an export gives the metadata (geo_meta, geo:status) of importing its dumps.
     */
    public function testAnImportedExportHasTheMetadataOfADirectImport(): void
    {
        $dumps = [self::DUMP, $this->olderDump()];
        $export = $this->temporary->path.'/two';
        (new FileExporter(new PhotonConverter()))->export($dumps, $export);
        $direct = new \PDO('sqlite::memory:');
        $fromExport = new \PDO('sqlite::memory:');
        $clock = new FrozenClock(new \DateTimeImmutable('2026-10-24 08:00:00 UTC'));

        foreach ([[$direct, new PhotonConverter(), $dumps], [$fromExport, new FileExportConverter(), [$export]]] as [$pdo, $converter, $paths]) {
            $importer = new Importer($converter, new InMemoryIndexManager(), new PdoBuildStorage($pdo), new TimestampIndexNaming(), new IndexSettings(), new TableNames(), $clock);
            $importer->publish($importer->import($paths));
        }

        $this->assertSame($this->metadata($direct), $this->metadata($fromExport));
        $this->assertSame('2026-10-10T20:00:00+00:00', Sql::value($fromExport, "SELECT meta_value FROM geo_meta WHERE meta_key = 'data_timestamp.SK'"));
        $this->assertStringNotContainsString('/', Sql::value($fromExport, "SELECT meta_value FROM geo_meta WHERE meta_key = 'dumps'"));
    }

    /**
     * Exports of 0.2 carry the paths of the machine that converted the dumps.
     */
    public function testKeepsOnlyTheFileNamesOfTheDumpsOfAnOlderExport(): void
    {
        $pdo = new \PDO('sqlite:'.$this->export.'/geocoder.sqlite');
        $statement = $pdo->prepare("UPDATE geo_meta SET meta_value = REPLACE(meta_value, ?, ?) WHERE meta_key = 'dumps'");
        $statement->execute(['"dump":"slovakia-sample.jsonl"', '"dump":"\/home\/builder\/dumps\/slovakia-sample.jsonl"']);
        $this->assertSame(1, $statement->rowCount());
        $pdo = null;

        $summaries = (new FileExportConverter())->convert($this->export, new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter());

        $this->assertSame('slovakia-sample.jsonl', $summaries[0]->dump);
    }

    public function testRejectsAnExportWhoseFilesDoNotMatchItsDumps(): void
    {
        $pdo = new \PDO('sqlite:'.$this->export.'/geocoder.sqlite');
        $pdo->exec('DELETE FROM geo_house WHERE rowid IN (SELECT rowid FROM geo_house LIMIT 4)');
        $pdo = null;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is damaged: its dumps hold 4 places, 13 streets, 4 points of interest and 134 house numbers, its files 4 places, 13 streets, 4 points of interest and 130 house numbers');

        (new FileExportConverter())->convert($this->export, new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter());
    }

    public function testLetsTheImporterIndexAnExport(): void
    {
        $indexes = new InMemoryIndexManager();
        $pdo = new \PDO('sqlite::memory:');
        $importer = new Importer(new FileExportConverter(), $indexes, new PdoBuildStorage($pdo), new TimestampIndexNaming(), new IndexSettings(), new TableNames(), new FrozenClock(new \DateTimeImmutable('2026-10-24 08:00:00 UTC')));

        $build = $importer->publish($importer->import([$this->export]));

        $this->assertCount(21, $indexes->writers['geo_20261024080000']->documents);
        $this->assertSame(134, $build->counts()->houseNumbers);
        $this->assertSame(134, (int) Sql::value($pdo, 'SELECT COUNT(*) FROM geo_house'));
    }

    public function testRejectsADirectoryThatIsNotAnExport(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not an export');

        (new FileExportConverter())->convert($this->temporary->path, new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter());
    }

    public function testRejectsAnIncompleteExport(): void
    {
        $pdo = new \PDO('sqlite:'.$this->export.'/geocoder.sqlite');
        $pdo->exec('DELETE FROM geo_meta');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a complete export');

        (new FileExportConverter())->convert($this->export, new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter());
    }

    public function testRejectsAnExportOfAnotherSchemaVersion(): void
    {
        $pdo = new \PDO('sqlite:'.$this->export.'/geocoder.sqlite');
        $pdo->exec("UPDATE geo_meta SET meta_value = '1' WHERE meta_key = 'schema_version'");

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('has the schema version 1');

        (new FileExportConverter())->convert($this->export, new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter());
    }

    public function testRejectsALineThatIsNotADocument(): void
    {
        file_put_contents($this->export.'/documents.ndjson', "\n{\"id\":\"N1\"}\n", \FILE_APPEND);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Line 23 of');

        (new FileExportConverter())->convert($this->export, new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter());
    }

    public function testRejectsAnInvalidProgressInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FileExportConverter(0);
    }

    /**
     * @return array<string, string>
     */
    private function metadata(\PDO $pdo): array
    {
        $statement = $pdo->query('SELECT meta_key, meta_value FROM geo_meta ORDER BY meta_key');
        $this->assertInstanceOf(\PDOStatement::class, $statement);

        return $statement->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /**
     * The sample under another name, with older data.
     */
    private function olderDump(): string
    {
        $dump = $this->temporary->path.'/austria-sample.jsonl';
        $contents = (string) file_get_contents(self::DUMP);
        file_put_contents($dump, str_replace('"data_timestamp":"2026-10-17T23:02:06.000+00:00"', '"data_timestamp":"2026-10-10T20:00:00.000+00:00"', $contents, $replaced));
        $this->assertSame(1, $replaced);

        return $dump;
    }
}
