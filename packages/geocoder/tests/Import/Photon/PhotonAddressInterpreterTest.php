<?php

namespace Tito10047\Geocoder\Tests\Import\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\Photon\PhotonAddressInterpreter;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Tests\Fake\Places;

final class PhotonAddressInterpreterTest extends TestCase
{
    /**
     * @param array<string, string> $address
     */
    #[DataProvider('provideTowns')]
    public function testTown(array $address, string $town, string $townOfPlace): void
    {
        $interpreter = new PhotonAddressInterpreter(['sk', 'hu']);
        $place = Places::create(address: $address);

        $this->assertSame($town, $interpreter->town($place));
        $this->assertSame($townOfPlace, $interpreter->townOfPlace($place));
    }

    /**
     * @return iterable<string, array{array<string, string>, string, string}>
     */
    public static function provideTowns(): iterable
    {
        yield 'city' => [['city' => 'Košice', 'town' => 'X', 'village' => 'Y'], 'Košice', 'Košice'];
        yield 'town' => [['town' => 'Moldava nad Bodvou', 'village' => 'Y'], 'Moldava nad Bodvou', 'Moldava nad Bodvou'];
        yield 'village' => [['village' => 'Viničky', 'locality' => 'Z'], 'Viničky', ''];
        yield 'locality' => [['locality' => 'Hatfa'], 'Hatfa', ''];
        yield 'none' => [['state' => 'Košický kraj'], '', ''];
    }

    /**
     * @param array<string, string> $address
     */
    #[DataProvider('provideStreets')]
    public function testStreet(array $address, string $street): void
    {
        $this->assertSame($street, (new PhotonAddressInterpreter([]))->street(Places::create(address: $address)));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function provideStreets(): iterable
    {
        yield 'street' => [['street' => 'Hlavná', 'place' => 'Viničky'], 'Hlavná'];
        yield 'place' => [['place' => 'Viničky'], 'Viničky'];
        yield 'none' => [['city' => 'Viničky'], ''];
    }

    public function testRegion(): void
    {
        $interpreter = new PhotonAddressInterpreter(['sk', 'hu', 'en']);

        $this->assertSame('Košický kraj', $interpreter->region(Places::create()));
        $this->assertSame(['hu' => 'Kassai kerület', 'en' => 'Region of Košice'], $interpreter->regionTranslations(Places::create()));
        $this->assertSame('', $interpreter->region(Places::create(address: [])));
        $this->assertSame([], $interpreter->regionTranslations(Places::create(address: ['state:hu' => 'Kassai kerület'])));
    }

    public function testSuburbAndCounty(): void
    {
        $interpreter = new PhotonAddressInterpreter(['sk', 'hu', 'en']);
        $place = Places::create(address: ['suburb' => 'Medvedzie', 'suburb:hu' => 'Medvéd', 'suburb:sk' => 'Medvedzie',
            'county' => 'okres Tvrdošín', 'county:en' => 'District of Tvrdošín', 'county:hu' => 'Turdossini járás', 'village' => 'Tvrdošín']);

        $this->assertSame('Medvedzie', $interpreter->suburb($place));
        $this->assertSame(['hu' => 'Medvéd'], $interpreter->suburbTranslations($place));
        $this->assertSame('okres Tvrdošín', $interpreter->county($place));
        $this->assertSame(['hu' => 'Turdossini járás', 'en' => 'District of Tvrdošín'], $interpreter->countyTranslations($place));

        $none = Places::create(address: ['suburb:hu' => 'Medvéd', 'county:hu' => 'Turdossini járás']);
        $this->assertSame('', $interpreter->suburb($none));
        $this->assertSame('', $interpreter->county($none));
        $this->assertSame([], $interpreter->suburbTranslations($none));
        $this->assertSame([], $interpreter->countyTranslations($none));
    }

    /**
     * @param array<string, string> $address
     * @param array<string, string> $expected
     */
    #[DataProvider('provideTownTranslations')]
    public function testTownTranslations(array $address, string $town, array $expected): void
    {
        $interpreter = new PhotonAddressInterpreter(['en', 'de', 'hu', 'sk']);

        $this->assertSame($expected, $interpreter->townTranslations(Places::create(address: $address), $town));
    }

    /**
     * @return iterable<string, array{array<string, string>, string, array<string, string>}>
     */
    public static function provideTownTranslations(): iterable
    {
        yield 'in the order of the languages, without the local name' => [
            ['city' => 'Košice', 'city:sk' => 'Košice', 'city:hu' => 'Kassa', 'city:de' => 'Kaschau', 'city:ru' => 'Кошице'],
            'Košice',
            ['de' => 'Kaschau', 'hu' => 'Kassa'],
        ];
        yield 'under the key of the town level' => [
            ['town' => 'Moldava nad Bodvou', 'town:hu' => 'Szepsi', 'city:hu' => 'Kassa'],
            'Moldava nad Bodvou',
            ['hu' => 'Szepsi'],
        ];
        yield 'village' => [['village' => 'Viničky', 'village:hu' => 'Szőlőske'], 'Viničky', ['hu' => 'Szőlőske']];
        // a locality has its translations under "village", which it has not
        yield 'locality' => [['locality' => 'Hatfa', 'locality:hu' => 'Hatfa-fürdő'], 'Hatfa', []];
        // a place is in the town "city ?? town", the translations come from the key the address has
        yield 'town of a place under the city key' => [['city' => 'Košice', 'city:hu' => 'Kassa'], 'Košice', ['hu' => 'Kassa']];
        yield 'no town' => [['city:hu' => 'Kassa'], '', []];
        yield 'empty translation' => [['city' => 'Košice', 'city:hu' => ''], 'Košice', []];
    }

    /**
     * @param array<string, string> $address
     * @param array<string, string> $expected
     */
    #[DataProvider('provideCatalogTranslations')]
    public function testCatalogTranslations(array $address, NameKind $kind, array $expected): void
    {
        $interpreter = new PhotonAddressInterpreter(['sk', 'de', 'hu', 'it']);

        $this->assertSame($expected, $interpreter->catalogTranslations(Places::create(osmKey: 'building', osmValue: 'yes', address: $address), $kind));
    }

    /**
     * @return iterable<string, array{array<string, string>, NameKind, array<string, string>}>
     */
    public static function provideCatalogTranslations(): iterable
    {
        yield 'town, the local name included' => [
            ['city' => 'Košice', 'city:sk' => 'Košice', 'city:hu' => 'Kassa', 'city:ru' => 'Кошице', 'city:it' => ''],
            NameKind::City,
            ['sk' => 'Košice', 'hu' => 'Kassa'],
        ];
        // the building in Bytča tagged "addr:city=Bratislava": the translations are those of Bytča
        yield 'town translated from the boundary of another' => [
            ['city' => 'Bratislava', 'city:sk' => 'Bytča', 'city:de' => 'Bitsch', 'city:hu' => 'Nagybiccse'],
            NameKind::City,
            [],
        ];
        yield 'town whose local name is translated in another language only' => [
            ['city' => 'Biel/Bienne', 'city:de' => 'Biel', 'city:fr' => 'Bienne', 'city:es' => 'Biel/Bienne'],
            NameKind::City,
            ['de' => 'Biel'],
        ];
        yield 'town under the key of its level' => [
            ['town' => 'Moldava nad Bodvou', 'town:sk' => 'Moldava nad Bodvou', 'town:hu' => 'Szepsi', 'city:hu' => 'Kassa'],
            NameKind::City,
            ['sk' => 'Moldava nad Bodvou', 'hu' => 'Szepsi'],
        ];
        yield 'part of a town' => [
            ['suburb' => 'Medvedzie', 'suburb:sk' => 'Medvedzie', 'suburb:hu' => 'Medvéd'],
            NameKind::Suburb,
            ['sk' => 'Medvedzie', 'hu' => 'Medvéd'],
        ];
        yield 'part of a town translated from the boundary of another' => [
            ['suburb' => 'Lomnica', 'suburb:sk' => 'Vranov nad Topľou', 'suburb:hu' => 'Varannó'],
            NameKind::Suburb,
            [],
        ];
        // counties and regions come from the boundaries: a canton named in two languages keeps its translations
        yield 'region named in two languages' => [
            ['state' => 'Bern/Berne', 'state:de' => 'Bern', 'state:it' => 'Berna'],
            NameKind::Region,
            ['de' => 'Bern', 'it' => 'Berna'],
        ];
        yield 'county' => [
            ['county' => 'okres Bytča', 'county:hu' => 'Nagybiccsei járás'],
            NameKind::County,
            ['hu' => 'Nagybiccsei járás'],
        ];
        yield 'no such name' => [['city' => 'Košice', 'city:hu' => 'Kassa'], NameKind::Suburb, []];
        yield 'country' => [['city' => 'Košice', 'city:hu' => 'Kassa'], NameKind::Country, []];
    }

    public function testRejectsInvalidLanguages(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PhotonAddressInterpreter(['SK']);
    }
}
