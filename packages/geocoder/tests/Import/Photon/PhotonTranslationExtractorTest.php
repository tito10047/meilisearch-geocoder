<?php

namespace Tito10047\Geocoder\Tests\Import\Photon;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Import\Photon\Country;
use Tito10047\Geocoder\Import\Photon\PhotonTranslationExtractor;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;
use Tito10047\Geocoder\Tests\Fake\Places;

final class PhotonTranslationExtractorTest extends TestCase
{
    public function testTranslationsOfTheTownAndTheRegion(): void
    {
        $extractor = new PhotonTranslationExtractor(['sk', 'en', 'hu']);

        $translations = $extractor->fromPlace(Places::create());

        $this->assertEquals([
            new Translation('SK', NameKind::City, 'Viničky', 'hu', 'Szőlőske'),
            new Translation('SK', NameKind::Region, 'Košický kraj', 'en', 'Region of Košice'),
            new Translation('SK', NameKind::Region, 'Košický kraj', 'hu', 'Kassai kerület'),
        ], $translations);
    }

    public function testTranslationsOfTheSuburbAndTheCounty(): void
    {
        $extractor = new PhotonTranslationExtractor(['hu']);

        $translations = $extractor->fromPlace(Places::create(address: ['suburb' => 'Medvedzie', 'suburb:hu' => 'Medvéd', 'village' => 'Tvrdošín',
            'county' => 'okres Tvrdošín', 'county:hu' => 'Turdossini járás']));

        $this->assertEquals([
            new Translation('SK', NameKind::Suburb, 'Medvedzie', 'hu', 'Medvéd'),
            new Translation('SK', NameKind::County, 'okres Tvrdošín', 'hu', 'Turdossini járás'),
        ], $translations);
    }

    public function testNoTranslationsWithoutAValidCountry(): void
    {
        $this->assertSame([], (new PhotonTranslationExtractor(['hu']))->fromPlace(Places::create(countryCode: '')));
    }

    public function testNoTranslationsWithoutTheLanguages(): void
    {
        $this->assertSame([], (new PhotonTranslationExtractor(['pl']))->fromPlace(Places::create()));
    }

    public function testTranslationsOfACountryAreAllKept(): void
    {
        $extractor = new PhotonTranslationExtractor(['sk', 'en', 'it']);
        $country = new Country('SK', ['name' => 'Slovensko', 'name:sk' => 'Slovensko', 'name:en' => 'Slovakia', 'name:fr' => 'Slovaquie', 'name:it' => '']);

        $this->assertEquals([
            new Translation('SK', NameKind::Country, 'SK', 'sk', 'Slovensko'),
            new Translation('SK', NameKind::Country, 'SK', 'en', 'Slovakia'),
        ], $extractor->fromCountry($country));
    }
}
