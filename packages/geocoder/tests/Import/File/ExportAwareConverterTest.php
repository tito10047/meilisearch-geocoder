<?php

namespace Tito10047\Geocoder\Tests\Import\File;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\File\ExportAwareConverter;
use Tito10047\Geocoder\Import\File\FileExporter;
use Tito10047\Geocoder\Import\Photon\PhotonConverter;
use Tito10047\Geocoder\Tests\Fake\InMemoryDocumentWriter;
use Tito10047\Geocoder\Tests\Fake\InMemoryHouseNumberWriter;
use Tito10047\Geocoder\Tests\Fake\InMemoryTranslationWriter;
use Tito10047\Geocoder\Tests\Fake\TemporaryDirectory;

final class ExportAwareConverterTest extends TestCase
{
    private const DUMP = __DIR__.'/../../Fixtures/photon/slovakia-sample.jsonl';

    private TemporaryDirectory $temporary;

    protected function setUp(): void
    {
        $this->temporary = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->temporary->remove();
    }

    public function testConvertsAnExportAndADumpAlike(): void
    {
        $export = $this->export();
        $converter = new ExportAwareConverter(new PhotonConverter());

        $fromDump = [new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter()];
        $dumpSummaries = $converter->convert(self::DUMP, ...$fromDump);
        $fromExport = [new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter()];
        $exportSummaries = $converter->convert($export, ...$fromExport);

        $this->assertEquals($fromDump[0]->documents, $fromExport[0]->documents);
        $this->assertEquals($fromDump[1]->houseNumbers, $fromExport[1]->houseNumbers);
        $this->assertEquals($dumpSummaries, $exportSummaries);
    }

    public function testDetectsAnExport(): void
    {
        $export = $this->export();
        $incomplete = $this->temporary->path.'/incomplete';
        mkdir($incomplete);
        touch($incomplete.'/documents.ndjson');

        $this->assertTrue(ExportAwareConverter::isExport($export));
        $this->assertFalse(ExportAwareConverter::isExport($incomplete));
        $this->assertFalse(ExportAwareConverter::isExport(self::DUMP));
        $this->assertFalse(ExportAwareConverter::isExport($this->temporary->path.'/missing'));
    }

    public function testAnyOtherDirectoryGoesToTheConverterOfDumps(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ExportAwareConverter(new PhotonConverter()))->convert($this->temporary->path, new InMemoryDocumentWriter(), new InMemoryHouseNumberWriter(), new InMemoryTranslationWriter());
    }

    private function export(): string
    {
        $export = $this->temporary->path.'/export';
        (new FileExporter(new PhotonConverter()))->export([self::DUMP], $export);

        return $export;
    }
}
