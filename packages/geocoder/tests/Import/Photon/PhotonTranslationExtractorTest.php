<?php

namespace Tito10047\Geocoder\Tests\Import\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Import\Photon\Country;
use Tito10047\Geocoder\Import\Photon\PhotonTranslationExtractor;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;
use Tito10047\Geocoder\Tests\Fake\Places;

final class PhotonTranslationExtractorTest extends TestCase
{
    /**
     * The address votes for the translations it gives, also for a name it does not translate.
     */
    public function testTranslationsOfTheTownAndTheRegion(): void
    {
        $extractor = new PhotonTranslationExtractor(['sk', 'en', 'hu']);

        $translations = $extractor->fromPlace(Places::create(osmKey: 'building', osmValue: 'yes'));

        $this->assertEquals([
            new Translation('SK', NameKind::City, 'Viničky', 'sk', 'Viničky'),
            new Translation('SK', NameKind::City, 'Viničky', 'hu', 'Szőlőske'),
            new Translation('SK', NameKind::Region, 'Košický kraj', 'en', 'Region of Košice'),
            new Translation('SK', NameKind::Region, 'Košický kraj', 'hu', 'Kassai kerület'),
        ], $translations);
    }

    public function testTranslationsOfTheSuburbAndTheCounty(): void
    {
        $extractor = new PhotonTranslationExtractor(['hu']);

        $translations = $extractor->fromPlace(Places::create(address: ['suburb' => 'Medvedzie', 'suburb:hu' => 'Medvéd', 'suburb:sk' => 'Medvedzie',
            'village' => 'Tvrdošín', 'county' => 'okres Tvrdošín', 'county:hu' => 'Turdossini járás']));

        $this->assertEquals([
            new Translation('SK', NameKind::Suburb, 'Medvedzie', 'hu', 'Medvéd'),
            new Translation('SK', NameKind::County, 'okres Tvrdošín', 'hu', 'Turdossini járás'),
        ], $translations);
    }

    /**
     * The case of Bytča: a building tagged "addr:city=Bratislava" in Bytča, which Photon translates
     * as Bytča (the boundary it lies in).
     */
    public function testNoTranslationsOfATownFromTheBoundaryOfAnother(): void
    {
        $extractor = new PhotonTranslationExtractor(['sk', 'de', 'hu']);

        $translations = $extractor->fromPlace(Places::create(osmKey: 'building', osmValue: 'yes', address: [
            'city' => 'Bratislava', 'city:sk' => 'Bytča', 'city:de' => 'Bitsch', 'city:hu' => 'Nagybiccse',
            'suburb' => 'Veľká Bytča', 'suburb:sk' => 'Veľká Bytča',
            'county' => 'okres Bytča', 'county:hu' => 'Nagybiccsei járás',
        ]));

        $this->assertEquals([
            new Translation('SK', NameKind::Suburb, 'Veľká Bytča', 'sk', 'Veľká Bytča'),
            new Translation('SK', NameKind::County, 'okres Bytča', 'hu', 'Nagybiccsei járás'),
        ], $translations);
    }

    public function testNoTranslationsWithoutAValidCountry(): void
    {
        $extractor = new PhotonTranslationExtractor(['hu']);

        $this->assertSame([], $extractor->fromPlace(Places::create(countryCode: '')));
        $this->assertSame([], $extractor->fromOwnNames(Places::create(countryCode: '')));
    }

    public function testNoTranslationsWithoutTheLanguages(): void
    {
        $this->assertSame([], (new PhotonTranslationExtractor(['pl']))->fromPlace(Places::create()));
    }

    /**
     * A town names itself in every language, by its local name where it has no other.
     */
    public function testOwnNamesOfATown(): void
    {
        $extractor = new PhotonTranslationExtractor(['sk', 'hu', 'pl']);

        $translations = $extractor->fromOwnNames(Places::create(osmValue: 'city', names: ['name' => 'Bratislava', 'name:hu' => 'Pozsony', 'name:pl' => '', 'name:de' => 'Pressburg']));

        $this->assertEquals([
            new Translation('SK', NameKind::City, 'Bratislava', 'sk', 'Bratislava'),
            new Translation('SK', NameKind::City, 'Bratislava', 'hu', 'Pozsony'),
            new Translation('SK', NameKind::City, 'Bratislava', 'pl', 'Bratislava'),
        ], $translations);
    }

    /**
     * A village is a town, and also a part of a town ("Hliník nad Váhom" of Bytča).
     */
    public function testOwnNamesOfAVillageNameATownAndAPartOfATown(): void
    {
        $translations = (new PhotonTranslationExtractor(['hu']))->fromOwnNames(Places::create());

        $this->assertEquals([
            new Translation('SK', NameKind::City, 'Viničky', 'hu', 'Szőlőske'),
            new Translation('SK', NameKind::Suburb, 'Viničky', 'hu', 'Szőlőske'),
        ], $translations);
    }

    /**
     * @param array<string, string> $names
     */
    #[DataProvider('notTowns')]
    public function testNoOwnNamesOfAnythingElse(string $osmKey, string $osmValue, array $names): void
    {
        $this->assertSame([], (new PhotonTranslationExtractor(['hu']))->fromOwnNames(Places::create(osmKey: $osmKey, osmValue: $osmValue, names: $names)));
    }

    /**
     * @return iterable<string, array{string, string, array<string, string>}>
     */
    public static function notTowns(): iterable
    {
        yield 'building' => ['building', 'yes', ['name' => 'Bratislava', 'name:hu' => 'Pozsony']];
        yield 'boundary' => ['boundary', 'administrative', ['name' => 'Bratislava', 'name:hu' => 'Pozsony']];
        yield 'locality' => ['place', 'locality', ['name' => 'Štvrte', 'name:hu' => 'Negyed']];
        yield 'nameless town' => ['place', 'town', ['name:hu' => 'Pozsony']];
    }

    public function testTranslationsOfACountryAreAllKept(): void
    {
        $extractor = new PhotonTranslationExtractor(['sk', 'en', 'it']);
        $country = new Country('SK', ['name' => 'Slovensko', 'name:sk' => 'Slovensko', 'name:en' => 'Slovakia', 'name:fr' => 'Slovaquie', 'name:it' => '']);

        $this->assertEquals([
            new Translation('SK', NameKind::Country, 'SK', 'sk', 'Slovensko'),
            new Translation('SK', NameKind::Country, 'SK', 'en', 'Slovakia'),
            new Translation('SK', NameKind::Country, 'SK', 'und', 'Slovensko'),
        ], $extractor->fromCountry($country));
    }

    public function testTheEnglishAndTheOwnNameOfACountryAreKeptWhateverTheLanguages(): void
    {
        $extractor = new PhotonTranslationExtractor(['hu']);
        $country = new Country('AT', ['name' => 'Österreich', 'name:hu' => 'Ausztria', 'name:en' => 'Austria', 'name:de' => 'Österreich']);

        $this->assertEquals([
            new Translation('AT', NameKind::Country, 'AT', 'hu', 'Ausztria'),
            new Translation('AT', NameKind::Country, 'AT', 'en', 'Austria'),
            new Translation('AT', NameKind::Country, 'AT', 'und', 'Österreich'),
        ], $extractor->fromCountry($country));
    }

    public function testACountryWithoutNamesHasNoTranslations(): void
    {
        $this->assertSame([], (new PhotonTranslationExtractor(['sk']))->fromCountry(new Country('XK', ['name:fr' => 'Kosovo', 'name' => ' '])));
    }
}
