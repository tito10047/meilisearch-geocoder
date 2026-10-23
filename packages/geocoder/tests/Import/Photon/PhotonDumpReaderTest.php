<?php

namespace Tito10047\Geocoder\Tests\Import\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidDumpException;
use Tito10047\Geocoder\Import\Photon\CountryInfo;
use Tito10047\Geocoder\Import\Photon\DumpHeader;
use Tito10047\Geocoder\Import\Photon\PhotonDumpReader;
use Tito10047\Geocoder\Import\Photon\Place;
use Tito10047\Geocoder\Tests\Fake\InMemoryLineSource;

final class PhotonDumpReaderTest extends TestCase
{
    private const HEADER = '{"type":"NominatimDumpFile","content":{"version":"0.1.0","generator":"photon","database_version":"1.0.0-4","data_timestamp":"2026-10-17T23:02:06.000+00:00","features":{"sorted_by_country":true,"has_addresslines":false}}}';
    private const PLACE = '{"place_id":"1","object_type":"W","object_id":135565007,"osm_key":"highway","osm_value":"residential","importance":0.05,"name":{"name":"Krížna","name:hu":"Kereszt utca","alt_name":5},"address":{"city":"Viničky","city:hu":"Szőlőske","state":"Košický kraj","other":["x"]},"postcode":"076 31","country_code":"sk","centroid":[21.7492543,48.3993171],"geometry":{"type":"LineString","coordinates":[[21.7,48.3],[21.8,48.4]]}}';

    public function testReadsTheRecordsOfADump(): void
    {
        $lines = new InMemoryLineSource([
            self::HEADER,
            '{"type":"CountryInfo","content":[{"country_code":"sk","name":{"name":"Slovensko","name:en":"Slovakia"}},{"country_code":"xyz","name":{"name":"?"}}]}',
            '{"type":"Place","content":['.self::PLACE.']}',
            '{"type":"FutureRecord","content":{}}',
        ]);

        $records = iterator_to_array((new PhotonDumpReader($lines))->read('sk.jsonl'), false);

        $this->assertSame(['sk.jsonl'], $lines->dumps);
        $this->assertCount(3, $records);

        $header = $records[0];
        $this->assertInstanceOf(DumpHeader::class, $header);
        $this->assertSame('0.1.0', $header->version);
        $this->assertSame('photon', $header->generator);
        $this->assertTrue($header->sortedByCountry);
        $this->assertEquals(new \DateTimeImmutable('2026-10-17T23:02:06+00:00'), $header->dataTimestamp);

        $countryInfo = $records[1];
        $this->assertInstanceOf(CountryInfo::class, $countryInfo);
        $this->assertCount(1, $countryInfo->countries);
        $this->assertSame('SK', $countryInfo->countries[0]->countryCode);
        $this->assertSame(['name' => 'Slovensko', 'name:en' => 'Slovakia'], $countryInfo->countries[0]->names);

        $place = $records[2];
        $this->assertInstanceOf(Place::class, $place);
        $this->assertSame('W', $place->objectType);
        $this->assertSame(135565007, $place->objectId);
        $this->assertSame('highway', $place->osmKey);
        $this->assertSame('residential', $place->osmValue);
        $this->assertEqualsWithDelta(0.05, $place->importance, \PHP_FLOAT_EPSILON);
        $this->assertSame(['name' => 'Krížna', 'name:hu' => 'Kereszt utca'], $place->names);
        $this->assertNull($place->houseNumber);
        $this->assertSame(['city' => 'Viničky', 'city:hu' => 'Szőlőske', 'state' => 'Košický kraj'], $place->address);
        $this->assertSame('076 31', $place->postcode);
        $this->assertSame('SK', $place->countryCode);
        $this->assertEqualsWithDelta(48.3993171, $place->centroid->latitude, \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(21.7492543, $place->centroid->longitude, \PHP_FLOAT_EPSILON);
    }

    public function testReadsEveryPlaceOfARecord(): void
    {
        $second = str_replace(['"object_id":135565007', '"name":{"name":"Krížna","name:hu":"Kereszt utca","alt_name":5},'], ['"object_id":"42"', ''], self::PLACE);
        $lines = new InMemoryLineSource([self::HEADER, '{"type":"Place","content":['.self::PLACE.','.$second.']}']);

        $records = iterator_to_array((new PhotonDumpReader($lines))->read('sk.jsonl'), false);

        $this->assertCount(3, $records);
        $this->assertInstanceOf(Place::class, $records[2]);
        $this->assertSame(42, $records[2]->objectId);
        $this->assertSame([], $records[2]->names);
    }

    public function testReadsAPlaceWithoutAnOsmObject(): void
    {
        $postcode = '{"osm_key":"place","osm_value":"postcode","name":{"name":"010 03"},"housenumber":"1","country_code":"sk","centroid":[18.7,49.2]}';
        $lines = new InMemoryLineSource([self::HEADER, '{"type":"Place","content":['.$postcode.']}']);

        $records = iterator_to_array((new PhotonDumpReader($lines))->read('sk.jsonl'), false);

        $this->assertInstanceOf(Place::class, $records[1]);
        $this->assertNull($records[1]->objectType);
        $this->assertNull($records[1]->objectId);
        $this->assertNull($records[1]->osmId());
        $this->assertSame('1', $records[1]->houseNumber);
        $this->assertEqualsWithDelta(0.0, $records[1]->importance, \PHP_FLOAT_EPSILON);
        $this->assertNull($records[1]->postcode);
    }

    public function testReadsTheFixture(): void
    {
        $places = 0;
        foreach ((new PhotonDumpReader())->read(__DIR__.'/../../Fixtures/photon/slovakia-sample.jsonl.zst') as $record) {
            $places += $record instanceof Place ? 1 : 0;
        }

        $this->assertSame(199, $places);
    }

    /**
     * @param list<string> $lines
     */
    #[DataProvider('provideInvalidDumps')]
    public function testRejectsAnInvalidDump(array $lines, int $line, string $message): void
    {
        try {
            iterator_to_array((new PhotonDumpReader(new InMemoryLineSource($lines)))->read('bad.jsonl'));
            $this->fail('The dump was read.');
        } catch (InvalidDumpException $exception) {
            $this->assertSame('bad.jsonl', $exception->dump);
            $this->assertSame($line, $exception->dumpLine);
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{list<string>, int, string}>
     */
    public static function provideInvalidDumps(): iterable
    {
        $place = static fn (string $json): array => [self::HEADER, '{"type":"Place","content":['.$json.']}'];

        yield 'empty' => [[], 0, 'it is empty'];
        yield 'invalid JSON' => [[self::HEADER, '{"type":'], 2, 'Line 2 of the dump "bad.jsonl": invalid JSON'];
        yield 'not an object' => [['"text"'], 1, 'must be a JSON object'];
        yield 'no header' => [['{"type":"Place","content":[]}'], 1, 'starts with a "NominatimDumpFile" header'];
        yield 'header without content' => [['{"type":"NominatimDumpFile"}'], 1, 'no content'];
        yield 'unsupported version' => [[str_replace('0.1.0', '1.0.0', self::HEADER)], 1, 'version "1.0.0" is not supported'];
        yield 'missing version' => [[str_replace('"version":"0.1.0",', '', self::HEADER)], 1, 'version "null" is not supported'];
        yield 'address lines' => [[str_replace('"has_addresslines":false', '"has_addresslines":true', self::HEADER)], 1, 'address lines'];
        yield 'no timestamp' => [[str_replace('"data_timestamp":"2026-10-17T23:02:06.000+00:00",', '', self::HEADER)], 1, 'no "data_timestamp"'];
        yield 'invalid timestamp' => [[str_replace('2026-10-17T23:02:06.000+00:00', 'yesterday at noon o\'clock', self::HEADER)], 1, 'is not a date'];
        yield 'second header' => [[self::HEADER, self::HEADER], 2, 'only one header'];
        yield 'content not a list' => [[self::HEADER, '{"type":"Place","content":{"a":1}}'], 2, 'must be a list'];
        yield 'content not objects' => [[self::HEADER, '{"type":"Place","content":[1]}'], 2, 'list of objects'];
        yield 'invalid object type' => [$place(str_replace('"object_type":"W"', '"object_type":1', self::PLACE)), 2, '"object_type" of the place must be a string'];
        yield 'invalid object id' => [$place(str_replace('"object_id":135565007', '"object_id":"W1"', self::PLACE)), 2, '"object_id" of the place must be a number'];
        yield 'type without id' => [$place(str_replace('"object_id":135565007,', '', self::PLACE)), 2, 'without an "object_id"'];
        yield 'no OSM key' => [$place(str_replace('"osm_key":"highway",', '', self::PLACE)), 2, 'no "osm_key" and "osm_value"'];
        yield 'no centroid' => [$place(str_replace('"centroid":[21.7492543,48.3993171],', '', self::PLACE)), 2, 'no "centroid"'];
        yield 'centroid of strings' => [$place(str_replace('[21.7492543,48.3993171]', '["21.7","48.3"]', self::PLACE)), 2, 'no "centroid"'];
        yield 'centroid out of range' => [$place(str_replace('[21.7492543,48.3993171]', '[21.7,98.3]', self::PLACE)), 2, 'latitude must be between'];
    }
}
