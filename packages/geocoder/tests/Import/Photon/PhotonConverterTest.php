<?php

namespace Tito10047\Geocoder\Tests\Import\Photon;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\InvalidDumpException;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Import\Photon\PhotonConverter;
use Tito10047\Geocoder\Import\Photon\PhotonDumpReader;
use Tito10047\Geocoder\Import\SelectionRules;
use Tito10047\Geocoder\Import\Workspace\SqliteConversionWorkspaceFactory;
use Tito10047\Geocoder\Tests\Fake\InMemoryDocumentWriter;
use Tito10047\Geocoder\Tests\Fake\InMemoryHouseNumberWriter;
use Tito10047\Geocoder\Tests\Fake\InMemoryLineSource;
use Tito10047\Geocoder\Tests\Fake\InMemoryTranslationWriter;
use Tito10047\Geocoder\Tests\Fake\RecordingProgress;
use Tito10047\Geocoder\Tests\Fake\TrackingWorkspaceFactory;

final class PhotonConverterTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../Fixtures/photon';
    private const HEADER = '{"type":"NominatimDumpFile","content":{"version":"0.1.0","data_timestamp":"2026-10-17T23:02:06.000+00:00"}}';

    private InMemoryDocumentWriter $documents;
    private InMemoryHouseNumberWriter $houseNumbers;
    private InMemoryTranslationWriter $translations;

    protected function setUp(): void
    {
        $this->documents = new InMemoryDocumentWriter();
        $this->houseNumbers = new InMemoryHouseNumberWriter();
        $this->translations = new InMemoryTranslationWriter();
    }

    /**
     * The sample gives what the prototype (variant D, with house numbers) gave for it.
     */
    public function testConvertsTheSampleLikeThePrototype(): void
    {
        $summary = (new PhotonConverter())->convert(self::FIXTURES.'/slovakia-sample.jsonl', $this->documents, $this->houseNumbers, $this->translations);

        $this->assertSame($this->expectedDocuments(), $this->documentLines());
        $this->assertSame($this->expectedHouseNumbers(), $this->houseNumberRows());

        $this->assertSame(self::FIXTURES.'/slovakia-sample.jsonl', $summary->dump);
        $this->assertEquals(new \DateTimeImmutable('2026-10-17T23:02:06+00:00'), $summary->dataTimestamp);
        $this->assertSame(['SK'], $summary->countryCodes);
        $this->assertEquals(new ImportCounts(places: 4, streets: 13, pois: 4, houseNumbers: 134), $summary->counts);
    }

    /**
     * The node N13544973832 is two records of the dump ("leisure=resort" and "tourism=zoo"):
     * one document, the first.
     */
    public function testWritesEachDocumentIdOnce(): void
    {
        (new PhotonConverter())->convert(self::FIXTURES.'/slovakia-sample.jsonl', $this->documents, $this->houseNumbers, $this->translations);

        $ids = array_map(static fn (Document $document): string => $document->id, $this->documents->documents);
        $this->assertSame(array_values(array_unique($ids)), $ids);

        $node = array_values(array_filter($this->documents->documents, static fn (Document $document): bool => 'N13544973832' === $document->id));
        $this->assertCount(1, $node);
        $this->assertSame('resort', $node[0]->type);
    }

    public function testConvertsACompressedDump(): void
    {
        $summary = (new PhotonConverter())->convert(self::FIXTURES.'/slovakia-sample.jsonl.zst', $this->documents, $this->houseNumbers, $this->translations);

        $this->assertSame($this->expectedDocuments(), $this->documentLines());
        $this->assertSame(134, $summary->counts->houseNumbers);
    }

    public function testWritesTheTranslationsOnce(): void
    {
        (new PhotonConverter())->convert(self::FIXTURES.'/slovakia-sample.jsonl', $this->documents, $this->houseNumbers, $this->translations);

        $translations = $this->translations->map();
        $this->assertCount(\count($this->translations->translations), $translations, 'Each translation is written once.');
        $this->assertSame('Szőlőske', $translations['SK|city|Viničky|hu']);
        $this->assertSame('Zsolna', $translations['SK|city|Žilina|hu']);
        $this->assertSame('Kassai kerület', $translations['SK|region|Košický kraj|hu']);
        $this->assertSame('Region of Košice', $translations['SK|region|Košický kraj|en']);
        $this->assertSame('Slovensko', $translations['SK|country|SK|sk']);
        $this->assertSame('Slovakia', $translations['SK|country|SK|en']);
        $this->assertSame('Ausztria', $translations['AT|country|AT|hu']);
        // only the languages of the rules, only translations that differ from the local name
        $this->assertArrayNotHasKey('SK|country|SK|fr', $translations);
        $this->assertArrayNotHasKey('SK|city|Viničky|sk', $translations);
        $this->assertArrayNotHasKey('SK|region|Košický kraj|ru', $translations);
    }

    public function testFollowsTheRules(): void
    {
        $rules = new SelectionRules(placeTypes: ['village' => 50], pois: ['amenity' => ['restaurant']], languages: ['hu']);

        $summary = (new PhotonConverter($rules))->convert(self::FIXTURES.'/slovakia-sample.jsonl', $this->documents, $this->houseNumbers, $this->translations);

        $this->assertSame(1, $summary->counts->places);
        // two restaurants "Zlatá putňa" at the same spot are one
        $this->assertSame(1, $summary->counts->pois);
        foreach ($this->translations->translations as $translation) {
            $this->assertSame('hu', $translation->language);
        }
    }

    /**
     * A named bus stop is no street; a street known from its houses is one whatever its bus stops.
     */
    public function testLeavesOutTheHighwaysThatAreNoStreets(): void
    {
        $place = static fn (int $id, string $key, string $value, string $name, string $extra = ''): string => \sprintf(
            '{"object_type":"N","object_id":%d,"osm_key":"%s","osm_value":"%s","name":{"name":"%s"},%s"address":{"village":"Nová Sedlica"},"country_code":"sk","centroid":[22.51,49.05]}',
            $id, $key, $value, $name, $extra,
        );
        $lines = [self::HEADER, '{"type":"Place","content":['.implode(',', [
            $place(1, 'highway', 'residential', 'Hlavná'),
            $place(2, 'highway', 'bus_stop', 'Nová Sedlica, stred'),
            $place(3, 'highway', 'platform', 'Nová Sedlica, stred'),
            $place(4, 'highway', 'bus_stop', 'Horná'),
        ]).']}', '{"type":"Place","content":[{"object_type":"W","object_id":5,"osm_key":"building","osm_value":"yes","housenumber":"12","address":{"village":"Nová Sedlica","street":"Horná"},"country_code":"sk","centroid":[22.51,49.05]}]}'];

        $summary = (new PhotonConverter(reader: new PhotonDumpReader(new InMemoryLineSource($lines))))
            ->convert('dump', $this->documents, $this->houseNumbers, $this->translations);

        $names = array_map(static fn (Document $document): string => $document->name, $this->documents->documents);
        sort($names);
        $this->assertSame(['Hlavná', 'Horná'], $names);
        $this->assertSame(2, $summary->counts->streets);
    }

    public function testDoesNotCloseTheWriters(): void
    {
        (new PhotonConverter())->convert(self::FIXTURES.'/slovakia-sample.jsonl', $this->documents, $this->houseNumbers, $this->translations);

        $this->assertFalse($this->documents->closed);
        $this->assertFalse($this->houseNumbers->closed);
        $this->assertFalse($this->translations->closed);
    }

    public function testReportsTheProgress(): void
    {
        $progress = new RecordingProgress();

        (new PhotonConverter(progressInterval: 50))->convert(self::FIXTURES.'/slovakia-sample.jsonl', $this->documents, $this->houseNumbers, $this->translations, $progress);

        // every 50 of the 199 places, and once at the end
        $this->assertCount(4, $progress->calls);
        foreach ($progress->calls as [$dump]) {
            $this->assertSame(self::FIXTURES.'/slovakia-sample.jsonl', $dump);
        }
        $this->assertEquals(new ImportCounts(places: 4, streets: 13, pois: 4, houseNumbers: 134), $progress->calls[3][1]);
        $this->assertSame(0, $progress->calls[0][1]->streets);
    }

    public function testClosesTheWorkspace(): void
    {
        $workspaces = new TrackingWorkspaceFactory(new SqliteConversionWorkspaceFactory());

        (new PhotonConverter(workspaces: $workspaces))->convert(self::FIXTURES.'/slovakia-sample.jsonl', $this->documents, $this->houseNumbers, $this->translations);

        $this->assertCount(1, $workspaces->workspaces);
        $this->assertTrue($workspaces->workspaces[0]->closed);
    }

    public function testClosesTheWorkspaceWhenTheDumpIsInvalid(): void
    {
        $workspaces = new TrackingWorkspaceFactory(new SqliteConversionWorkspaceFactory());
        $reader = new PhotonDumpReader(new InMemoryLineSource([self::HEADER, '{"type":"Place","content":[{"osm_key":"place"}]}']));

        try {
            (new PhotonConverter(reader: $reader, workspaces: $workspaces))->convert('bad.jsonl', $this->documents, $this->houseNumbers, $this->translations);
            $this->fail('The dump was converted.');
        } catch (InvalidDumpException $exception) {
            $this->assertSame(2, $exception->dumpLine);
        }

        $this->assertTrue($workspaces->workspaces[0]->closed);
    }

    public function testRejectsAMissingDump(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PhotonConverter())->convert(self::FIXTURES.'/missing.jsonl', $this->documents, $this->houseNumbers, $this->translations);
    }

    public function testRejectsAnInvalidProgressInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PhotonConverter(progressInterval: 0);
    }

    /**
     * @return list<string>
     */
    private function documentLines(): array
    {
        $lines = array_map(static fn (Document $document): string => json_encode($document->toArray(), \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), $this->documents->documents);
        sort($lines);

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function expectedDocuments(): array
    {
        $lines = file(self::FIXTURES.'/expected-documents.ndjson', \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES);
        $this->assertIsArray($lines);

        return $lines;
    }

    /**
     * @return list<list<string>>
     */
    private function houseNumberRows(): array
    {
        $rows = array_map(static fn (HouseNumber $houseNumber): array => [
            (string) $houseNumber->streetKey->value,
            $houseNumber->number,
            $houseNumber->postcode ?? '',
            json_encode($houseNumber->coordinates->latitude, \JSON_THROW_ON_ERROR),
            json_encode($houseNumber->coordinates->longitude, \JSON_THROW_ON_ERROR),
            $houseNumber->suburb ?? '',
            $houseNumber->reference ?? '',
        ], $this->houseNumbers->houseNumbers);
        usort($rows, static fn (array $a, array $b): int => [(int) $a[0], ...\array_slice($a, 1)] <=> [(int) $b[0], ...\array_slice($b, 1)]);

        return $rows;
    }

    /**
     * @return list<list<string|null>>
     */
    private function expectedHouseNumbers(): array
    {
        $handle = fopen(self::FIXTURES.'/expected-house-numbers.csv', 'r');
        $this->assertIsResource($handle);
        fgetcsv($handle, escape: '');
        $rows = [];
        while (false !== ($row = fgetcsv($handle, escape: ''))) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
