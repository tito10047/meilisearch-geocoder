<?php

namespace Tito10047\Geocoder\Tests\Search;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\Search\AddressLookup;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\LabelFormatter;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;
use Tito10047\Geocoder\StreetKey;
use Tito10047\Geocoder\Tests\Fake\Documents;
use Tito10047\Geocoder\Tests\Fake\FakeHouseNumberStore;
use Tito10047\Geocoder\Tests\Fake\FakeNameCatalog;
use Tito10047\Geocoder\Tests\Fake\FakeSearchBackend;

final class AddressLookupTest extends TestCase
{
    private const STREET = 909109604356383787;
    private const TRANSLATIONS = [
        'SK|country|SK|hu' => 'Szlovákia',
        'SK|city|Tvrdošín|hu' => 'Turdossin',
        'SK|county|okres Tvrdošín|hu' => 'Turdossini járás',
        'SK|suburb|Medvedzie|hu' => 'Medvéd',
    ];

    public function testFindsTheAddressOfAReference(): void
    {
        $lookup = $this->lookup([
            new HouseNumber(new StreetKey(self::STREET), '606/42', '027 44', new Coordinates(49.33, 19.55), 'Medvedzie', '7101722639'),
            new HouseNumber(new StreetKey(self::STREET), '12', null, new Coordinates(49.34, 19.56), null, '7101722640'),
        ]);

        $address = $lookup->findByReference(' 7101722639 ');

        $this->assertInstanceOf(Suggestion::class, $address);
        $this->assertSame('s'.self::STREET.'#606/42', $address->id);
        $this->assertSame(Kind::Address, $address->kind);
        $this->assertSame('house', $address->type);
        $this->assertSame('Hlavná 606/42', $address->name);
        $this->assertSame('Hlavná 606/42, Tvrdošín, SK', $address->label);
        $this->assertSame('Hlavná', $address->street);
        $this->assertSame('606/42', $address->houseNumber);
        $this->assertSame('Medvedzie', $address->suburb);
        $this->assertSame('Tvrdošín', $address->city);
        $this->assertSame('okres Tvrdošín', $address->county);
        $this->assertSame('Žilinský kraj', $address->region);
        $this->assertSame('027 44', $address->postcode);
        $this->assertSame('SK', $address->countryCode);
        $this->assertSame('7101722639', $address->reference);
        $this->assertNull($address->match, 'Nothing was searched by name.');
        $this->assertEqualsWithDelta(49.33, $address->coordinates->latitude, \PHP_FLOAT_EPSILON);

        // without a suburb of its own, the house is in the suburb of its street
        $other = $lookup->findByReference('7101722640');
        $this->assertSame('Brestovec', $other?->suburb);
        $this->assertSame('027 01', $other->postcode);
    }

    public function testNamesTheAddressInTheLanguage(): void
    {
        $lookup = $this->lookup([new HouseNumber(new StreetKey(self::STREET), '606/42', null, new Coordinates(49.33, 19.55), 'Medvedzie', '7101722639')]);

        $address = $lookup->findByReference('7101722639', new SearchOptions(locale: 'hu_HU'));

        $this->assertSame('Hlavná 606/42, Turdossin, Szlovákia', $address?->label);
        $this->assertSame('Medvéd', $address->suburb);
        $this->assertSame('Turdossini járás', $address->county);
    }

    public function testAnUnknownReferenceIsNoAddress(): void
    {
        $lookup = $this->lookup([new HouseNumber(new StreetKey(self::STREET), '1', null, new Coordinates(49.33, 19.55), null, '1')]);

        $this->assertNull($lookup->findByReference('2'));
        $this->assertNull($lookup->findByReference(' '));
    }

    public function testAHouseWithoutItsStreetDocumentIsNoAddress(): void
    {
        $lookup = $this->lookup([new HouseNumber(new StreetKey(1), '1', null, new Coordinates(49.33, 19.55), null, '1')]);

        $this->assertNull($lookup->findByReference('1'));
    }

    /**
     * @param list<HouseNumber> $houseNumbers
     */
    private function lookup(array $houseNumbers): AddressLookup
    {
        $street = Documents::create('s'.self::STREET, 'Hlavná', 'SK', DocumentKind::Street, 'street', 25000, 'Tvrdošín', 'Žilinský kraj',
            postcode: '027 01', suburb: 'Brestovec', county: 'okres Tvrdošín');
        $catalog = new FakeNameCatalog(self::TRANSLATIONS);

        return new AddressLookup(new FakeHouseNumberStore($houseNumbers), new FakeSearchBackend([$street]), $catalog, new LabelFormatter($catalog));
    }
}
