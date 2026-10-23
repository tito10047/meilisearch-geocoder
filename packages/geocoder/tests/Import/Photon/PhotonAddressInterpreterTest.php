<?php

namespace Tito10047\Geocoder\Tests\Import\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\Photon\PhotonAddressInterpreter;
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

    public function testRejectsInvalidLanguages(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PhotonAddressInterpreter(['SK']);
    }
}
