<?php

namespace Tito10047\Geocoder\Tests\Document;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Exception\InvalidArgumentException;

final class DocumentTest extends TestCase
{
    public function testToArrayIsTheIndexFormat(): void
    {
        $this->assertSame([
            'id' => 's791028875740832172',
            'kind' => 'street',
            'type' => 'street',
            'name' => 'Stephansplatz',
            'names' => ['Stephansplatz'],
            'cities' => ['Wien', 'Viedeň', 'Vienna'],
            'suburb' => '',
            'city' => 'Wien',
            'county' => '',
            'region' => '',
            'cc' => 'AT',
            'postcode' => null,
            '_geo' => ['lat' => 48.2092503, 'lng' => 16.3726684],
            'rank' => 25000,
        ], $this->street()->toArray());
    }

    public function testToArrayKeepsTranslations(): void
    {
        $document = new Document('R109166', DocumentKind::Place, 'city', 'Wien', ['Wien', 'Viedeň'], [], ['sk' => 'Viedeň'],
            '', '', 'AT', '1010', new Coordinates(48.2083537, 16.3725042), 90767);

        $this->assertSame(['sk' => 'Viedeň'], $document->toArray()['names_i18n'] ?? null);
    }

    public function testFromArrayRoundTrips(): void
    {
        $document = new Document('R109166', DocumentKind::Place, 'city', 'Wien', ['Wien', 'Viedeň'], [], ['sk' => 'Viedeň'],
            '', 'Wien', 'AT', '1010', new Coordinates(48.2083537, 16.3725042), 90767, 'Innere Stadt', 'Wien');

        $this->assertEquals($document, Document::fromArray($document->toArray()));
        $this->assertEquals($document, Document::fromArray(json_decode(json_encode($document->toArray(), \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR)));
    }

    public function testFromArrayAcceptsADocumentWithoutSuburbAndCounty(): void
    {
        $document = $this->street()->toArray();
        unset($document['suburb'], $document['county']);

        $this->assertEquals($this->street(), Document::fromArray($document));
    }

    public function testFromArrayAcceptsAHitWithoutSearchableFields(): void
    {
        $hit = $this->street()->toArray();
        unset($hit['names'], $hit['cities'], $hit['city'], $hit['region']);
        $hit['_geo'] = ['lat' => 48, 'lng' => 16];

        $document = Document::fromArray($hit);

        $this->assertSame([], $document->names);
        $this->assertSame([], $document->cities);
        $this->assertSame('', $document->city);
        $this->assertEqualsWithDelta(48.0, $document->coordinates->latitude, \PHP_FLOAT_EPSILON);
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('provideInvalidArrays')]
    public function testFromArrayRejectsInvalidDocuments(array $changes, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        Document::fromArray(array_merge($this->street()->toArray(), $changes));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidArrays(): iterable
    {
        yield 'no geo' => [['_geo' => null], '"_geo"'];
        yield 'unknown kind' => [['kind' => 'house'], 'unknown kind "house"'];
        yield 'kind not a string' => [['kind' => 1], '"kind"'];
        yield 'names not a list' => [['names' => ['a' => 'b']], '"names"'];
        yield 'names not strings' => [['names' => [1]], '"names"'];
        yield 'translations not strings' => [['names_i18n' => ['sk' => 1]], '"names_i18n"'];
        yield 'translations not a map' => [['names_i18n' => 'x'], '"names_i18n"'];
        yield 'postcode not a string' => [['postcode' => 1010], '"postcode"'];
        yield 'latitude not a number' => [['_geo' => ['lat' => '48', 'lng' => 16]], '"lat"'];
        yield 'rank not an integer' => [['rank' => 1.5], '"rank"'];
        yield 'invalid country' => [['cc' => 'at'], 'two upper case letters'];
    }

    #[DataProvider('provideInvalidValues')]
    public function testRejectsInvalidValues(string $id, string $name, string $countryCode, int $rank, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new Document($id, DocumentKind::Poi, 'hotel', $name, [$name], [], [], '', '', $countryCode, null, new Coordinates(0, 0), $rank);
    }

    /**
     * @return iterable<string, array{string, string, string, int, string}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'empty id' => ['', 'Hotel', 'SK', 0, 'id'];
        yield 'empty name' => ['N1', '', 'SK', 0, 'name'];
        yield 'lower case country' => ['N1', 'Hotel', 'sk', 0, 'country code'];
        yield 'negative rank' => ['N1', 'Hotel', 'SK', -1, 'rank'];
    }

    private function street(): Document
    {
        return new Document('s791028875740832172', DocumentKind::Street, 'street', 'Stephansplatz', ['Stephansplatz'],
            ['Wien', 'Viedeň', 'Vienna'], [], 'Wien', '', 'AT', null, new Coordinates(48.2092503, 16.3726684), 25000);
    }
}
