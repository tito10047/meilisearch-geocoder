<?php

namespace Tito10047\Geocoder\Tests\Import\File;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
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

        $summary = (new FileExportConverter(progressInterval: 10))->convert($this->export, ...[...$fromExport, $progress]);

        $this->assertEquals($fromDump[0]->documents, $fromExport[0]->documents);
        $this->assertEquals($fromDump[1]->houseNumbers, $fromExport[1]->houseNumbers);
        $this->assertEquals($fromDump[2]->translations, $fromExport[2]->translations);
        $this->assertSame($this->export, $summary->dump);
        $this->assertEquals(new \DateTimeImmutable('2026-10-17T23:02:06+00:00'), $summary->dataTimestamp);
        $this->assertSame(['SK'], $summary->countryCodes);
        $this->assertEquals(new ImportCounts(4, 13, 4, 134), $summary->counts);
        $this->assertGreaterThan(5, \count($progress->calls));
        $this->assertEquals([$this->export, $summary->counts], $progress->calls[\count($progress->calls) - 1]);
        $this->assertFalse($fromExport[0]->closed);
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
}
