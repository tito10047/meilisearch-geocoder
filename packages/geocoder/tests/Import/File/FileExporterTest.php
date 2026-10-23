<?php

namespace Tito10047\Geocoder\Tests\Import\File;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\Import\File\FileExporter;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Import\Photon\PhotonConverter;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\FailingConverter;
use Tito10047\Geocoder\Tests\Fake\FrozenClock;
use Tito10047\Geocoder\Tests\Fake\Sql;
use Tito10047\Geocoder\Tests\Fake\TemporaryDirectory;

final class FileExporterTest extends TestCase
{
    private const DUMP = __DIR__.'/../../Fixtures/photon/slovakia-sample.jsonl';

    private TemporaryDirectory $temporary;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->temporary = new TemporaryDirectory();
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-10-23 12:00:00 UTC'));
    }

    protected function tearDown(): void
    {
        $this->temporary->remove();
    }

    public function testExportsTheDocumentsAndTheTables(): void
    {
        $directory = $this->temporary->path.'/export';

        $build = $this->exporter()->export([self::DUMP], $directory);

        $this->assertSame('geo_20261023120000', $build->indexUid);
        $this->assertEquals(new TableNames(), $build->tables);
        $this->assertSame((new IndexSettings())->hash(), $build->settingsHash);
        $this->assertEquals(new ImportCounts(4, 13, 4, 134), $build->counts());

        $lines = file($directory.'/documents.ndjson', \FILE_IGNORE_NEW_LINES);
        $this->assertIsArray($lines);
        $this->assertCount(21, $lines);
        $pdo = new \PDO('sqlite:'.$directory.'/geocoder.sqlite');
        $this->assertEquals($build, (new PdoBuildStorage($pdo))->read(new TableNames()));
        $this->assertSame(134, (int) Sql::value($pdo, 'SELECT COUNT(*) FROM geo_house'));
        $this->assertSame(['documents.ndjson', 'geocoder.sqlite'], array_values(array_diff(scandir($directory) ?: [], ['.', '..'])));
    }

    public function testExportsIntoAnEmptyDirectory(): void
    {
        $this->exporter()->export([self::DUMP], $this->temporary->path);

        $this->assertFileExists($this->temporary->path.'/geocoder.sqlite');
    }

    public function testRefusesADirectoryThatIsNotEmpty(): void
    {
        touch($this->temporary->path.'/other');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not empty');

        $this->exporter()->export([self::DUMP], $this->temporary->path);
    }

    public function testRejectsAnExportWithoutDumps(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->exporter()->export([], $this->temporary->path);
    }

    public function testRemovesTheFilesWhenTheExportFails(): void
    {
        $directory = $this->temporary->path.'/export';
        $failure = new RuntimeException('Failed.');
        $exporter = new FileExporter(new FailingConverter(new PhotonConverter(), [self::DUMP], $failure), new TimestampIndexNaming(), new IndexSettings(), $this->clock);

        try {
            $exporter->export([self::DUMP], $directory);
            $this->fail('The export did not fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }

        $this->assertDirectoryDoesNotExist($directory);
    }

    public function testWrapsAFailureFromOutsideThePackage(): void
    {
        $failure = new \UnexpectedValueException('Failed.');
        $exporter = new FileExporter(new FailingConverter(new PhotonConverter(), [self::DUMP], $failure), clock: $this->clock);

        try {
            $exporter->export([self::DUMP], $this->temporary->path);
            $this->fail('The export did not fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception->getPrevious());
        }

        // the directory existed before: it stays, empty
        $this->assertSame(['.', '..'], scandir($this->temporary->path));
    }

    private function exporter(): FileExporter
    {
        return new FileExporter(new PhotonConverter(), new TimestampIndexNaming(), new IndexSettings(), $this->clock);
    }
}
