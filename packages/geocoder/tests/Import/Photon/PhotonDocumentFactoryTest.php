<?php

namespace Tito10047\Geocoder\Tests\Import\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Import\Photon\PhotonDocumentFactory;
use Tito10047\Geocoder\Import\SelectionRules;
use Tito10047\Geocoder\StreetKey;
use Tito10047\Geocoder\Tests\Fake\Places;

final class PhotonDocumentFactoryTest extends TestCase
{
    private const KOSICE = ['city' => 'Košice', 'city:sk' => 'Košice', 'city:hu' => 'Kassa', 'city:de' => 'Kaschau', 'state' => 'Košický kraj', 'state:hu' => 'Kassai kerület'];

    public function testPlace(): void
    {
        $factory = new PhotonDocumentFactory(new SelectionRules());
        $place = Places::create(objectType: 'R', objectId: 1690324, osmValue: 'city', importance: 0.6567,
            names: ['name' => 'Košice', 'name:sk' => 'Košice', 'name:hu' => 'Kassa', 'name:de' => 'Kaschau', 'name:pl' => 'Koszyce', 'name:fr' => 'Cassovie', 'name:cs' => 'Kaschau'],
            address: ['state' => 'Košický kraj'], postcode: null, latitude: 48.697265, longitude: 21.2644253);

        $document = $factory->place($place);

        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame([
            'id' => 'R1690324',
            'kind' => 'place',
            'type' => 'city',
            'name' => 'Košice',
            'names' => ['Košice', 'Kaschau', 'Kassa', 'Koszyce'],
            'cities' => [],
            'suburb' => '',
            'city' => '',
            'county' => '',
            'region' => 'Košický kraj',
            'cc' => 'SK',
            'postcode' => null,
            '_geo' => ['lat' => 48.697265, 'lng' => 21.2644253],
            'rank' => 90656,
            'names_i18n' => ['de' => 'Kaschau', 'hu' => 'Kassa', 'cs' => 'Kaschau', 'pl' => 'Koszyce'],
        ], $document->toArray());
    }

    public function testPlaceOfAVillageIsInItsTown(): void
    {
        $factory = new PhotonDocumentFactory(new SelectionRules());

        $document = $factory->place(Places::create(osmValue: 'village', importance: 0.48));

        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame('Viničky', $document->city);
        $this->assertSame(['Viničky', 'Szőlőske'], $document->cities);
        $this->assertSame(50480, $document->rank);
        $this->assertSame('076 31', $document->postcode);
    }

    public function testNoPlaceWithoutWhatItNeeds(): void
    {
        $factory = new PhotonDocumentFactory(new SelectionRules());

        $this->assertNull($factory->place(Places::create(osmValue: 'locality')));
        $this->assertNull($factory->place(Places::create(names: [])));
        $this->assertNull($factory->place(Places::create(countryCode: '')));
        $this->assertNull($factory->place(Places::create(objectType: null, objectId: null)));
    }

    public function testPoi(): void
    {
        $factory = new PhotonDocumentFactory(new SelectionRules());
        $place = Places::create(objectType: 'N', objectId: 12714307377, osmKey: 'tourism', osmValue: 'guest_house', importance: 0.0,
            names: ['name' => 'Tokaj Guest House'], address: ['street' => 'Krížna', 'village' => 'Viničky', 'village:hu' => 'Szőlőske', 'state' => 'Košický kraj']);

        $document = $factory->poi($place);

        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame('N12714307377', $document->id);
        $this->assertSame(DocumentKind::Poi, $document->kind);
        $this->assertSame('guest_house', $document->type);
        $this->assertSame('Viničky', $document->city);
        $this->assertSame(['Viničky', 'Szőlőske'], $document->cities);
        $this->assertSame([], $document->namesI18n);
        $this->assertSame(20000, $document->rank);
        $this->assertNull($factory->poi(Places::create(names: [])));
    }

    public function testStreet(): void
    {
        $factory = new PhotonDocumentFactory(new SelectionRules());
        $place = Places::create(objectType: 'W', objectId: 1, osmKey: 'highway', osmValue: 'primary', importance: 0.3,
            names: ['name' => 'Hlavná', 'name:hu' => 'Fő utca'], address: ['suburb' => 'Staré Mesto', 'county' => 'okres Košice I'] + self::KOSICE, postcode: '040 01');

        $document = $factory->street($place);

        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame([
            'id' => StreetKey::fromAddress('SK', 'Košice', 'Hlavná')->documentId(),
            'kind' => 'street',
            'type' => 'street',
            'name' => 'Hlavná',
            'names' => ['Hlavná', 'Fő utca'],
            'cities' => ['Košice', 'Kaschau', 'Kassa'],
            'suburb' => 'Staré Mesto',
            'city' => 'Košice',
            'county' => 'okres Košice I',
            'region' => 'Košický kraj',
            'cc' => 'SK',
            'postcode' => null,
            '_geo' => ['lat' => 48.3996662, 'lng' => 21.747595],
            'rank' => 25000,
            'names_i18n' => ['hu' => 'Fő utca'],
        ], $document->toArray());
        $this->assertNull($factory->street(Places::create(names: [])));
        $this->assertNull($factory->street(Places::create(countryCode: 'S')));
    }

    public function testStreetOfAddress(): void
    {
        $factory = new PhotonDocumentFactory(new SelectionRules(streetRank: 30));
        $place = Places::create(osmKey: 'building', osmValue: 'yes', names: [], houseNumber: '606/42', address: ['street' => 'Hlavná', 'suburb' => 'Sever', 'county' => 'okres Košice I'] + self::KOSICE);

        $document = $factory->streetOfAddress($place);

        $this->assertInstanceOf(Document::class, $document);
        $this->assertSame(StreetKey::fromAddress('SK', 'Košice', 'Hlavná')->documentId(), $document->id);
        $this->assertSame(['Hlavná'], $document->names);
        $this->assertSame(['Košice', 'Kaschau', 'Kassa'], $document->cities);
        $this->assertSame([], $document->namesI18n);
        $this->assertNull($document->postcode);
        $this->assertSame(30000, $document->rank);
        $this->assertSame('Sever', $document->suburb);
        $this->assertSame('okres Košice I', $document->county);

        $named = $factory->streetOfAddress(Places::create(houseNumber: '1', address: ['place' => 'Hatfa', 'village' => 'Viničky']));
        $this->assertSame('Hatfa', $named?->name);
        $this->assertNull($factory->streetOfAddress(Places::create(houseNumber: '1', address: ['city' => 'Košice'])));
        $this->assertNull($factory->streetOfAddress(Places::create(houseNumber: '1', address: ['street' => 'Hlavná'], countryCode: '')));
    }

    public function testHouseNumber(): void
    {
        $factory = new PhotonDocumentFactory(new SelectionRules());

        $houseNumber = $factory->houseNumber(Places::create(houseNumber: '606/42', address: ['street' => 'Hlavná'] + self::KOSICE, postcode: '040 01'));

        $this->assertInstanceOf(\Tito10047\Geocoder\HouseNumber\HouseNumber::class, $houseNumber);
        $this->assertEquals(StreetKey::fromAddress('SK', 'Košice', 'Hlavná'), $houseNumber->streetKey);
        $this->assertSame('606/42', $houseNumber->number);
        $this->assertSame('42', $houseNumber->orientation);
        $this->assertSame('040 01', $houseNumber->postcode);
        $this->assertEqualsWithDelta(48.3996662, $houseNumber->coordinates->latitude, \PHP_FLOAT_EPSILON);

        // an address without a street is kept under the street "", like the prototype does
        $withoutStreet = $factory->houseNumber(Places::create(houseNumber: '7', address: ['city' => 'Košice']));
        $this->assertEquals(StreetKey::fromAddress('SK', 'Košice', ''), $withoutStreet?->streetKey);

        $this->assertNull($factory->houseNumber(Places::create()));
        $this->assertNull($factory->houseNumber(Places::create(houseNumber: ' ')));
        $this->assertNull($houseNumber->suburb);
        $this->assertNull($houseNumber->reference);
    }

    public function testHouseNumberWithSuburbAndReference(): void
    {
        $factory = new PhotonDocumentFactory(new SelectionRules());
        $address = ['street' => 'Hlavná', 'suburb' => 'Staré Mesto'] + self::KOSICE;

        $houseNumber = $factory->houseNumber(Places::create(houseNumber: '606/42', address: $address, extra: ['ref:minvskaddress' => ' 7101722639 ', 'building:levels' => '3']));

        $this->assertSame('Staré Mesto', $houseNumber?->suburb);
        $this->assertSame('7101722639', $houseNumber->reference);
    }

    /**
     * @param array<string, string> $extra
     */
    #[DataProvider('provideReferencesLeftOut')]
    public function testLeavesOutTheReference(string $countryCode, array $extra): void
    {
        $factory = new PhotonDocumentFactory(new SelectionRules());

        $houseNumber = $factory->houseNumber(Places::create(houseNumber: '1', address: ['street' => 'Hlavná'] + self::KOSICE, countryCode: $countryCode, extra: $extra));

        $this->assertNull($houseNumber?->reference);
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function provideReferencesLeftOut(): iterable
    {
        yield 'none' => ['SK', []];
        yield 'empty' => ['SK', ['ref:minvskaddress' => ' ']];
        yield 'too long for the table' => ['SK', ['ref:minvskaddress' => str_repeat('7', 33)]];
        yield 'the key of another country' => ['CZ', ['ref:minvskaddress' => '7101722639']];
    }

    public function testFollowsTheReferenceKeys(): void
    {
        $factory = new PhotonDocumentFactory(new SelectionRules(referenceKeys: ['CZ' => 'ref:ruian:addr']));

        $houseNumber = $factory->houseNumber(Places::create(houseNumber: '1', address: ['street' => 'Hlavná'] + self::KOSICE, countryCode: 'CZ', extra: ['ref:ruian:addr' => '21705909']));

        $this->assertSame('21705909', $houseNumber?->reference);
    }
}
