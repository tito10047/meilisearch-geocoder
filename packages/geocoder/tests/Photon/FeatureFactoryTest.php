<?php

namespace Tito10047\Geocoder\Tests\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Import\SelectionRules;
use Tito10047\Geocoder\Photon\FeatureFactory;
use Tito10047\Geocoder\Search\Suggestion;
use Tito10047\Geocoder\Tests\Fake\FakeNameCatalog;
use Tito10047\Geocoder\Tests\Fake\Suggestions;

final class FeatureFactoryTest extends TestCase
{
    /**
     * @param array<string, string|int> $properties
     */
    #[DataProvider('provideSuggestions')]
    public function testMapsTheSuggestion(Suggestion $suggestion, ?string $language, array $properties): void
    {
        $factory = new FeatureFactory(new FakeNameCatalog(['SK|country|SK|sk' => 'Slovensko', 'SK|country|SK|en' => 'Slovakia']));

        $json = $factory->create($suggestion, $language)->jsonSerialize();

        $this->assertSame($properties, $json['properties']);
        $this->assertSame([$suggestion->coordinates->longitude, $suggestion->coordinates->latitude], $json['geometry']['coordinates']);
    }

    /**
     * @return iterable<string, array{Suggestion, string|null, array<string, string|int>}>
     */
    public static function provideSuggestions(): iterable
    {
        yield 'city' => [Suggestions::city(), 'sk', [
            'osm_type' => 'N', 'osm_id' => 530544342, 'osm_key' => 'place', 'osm_value' => 'city', 'type' => 'city',
            'name' => 'Bratislava', 'state' => 'Bratislavský kraj', 'country' => 'Slovensko',
            'postcode' => '815 18', 'countrycode' => 'SK',
        ]];
        yield 'village is a district, as in Photon' => [Suggestions::village(), null, [
            'osm_type' => 'N', 'osm_id' => 26177645, 'osm_key' => 'place', 'osm_value' => 'village', 'type' => 'district',
            'name' => 'Viničky', 'city' => 'Viničky', 'state' => 'Košický kraj', 'postcode' => '076 31', 'countrycode' => 'SK',
        ]];
        yield 'street merged from ways has no OSM id nor value' => [Suggestions::street(), 'en', [
            'osm_key' => 'highway', 'type' => 'street', 'name' => 'Hlavná', 'city' => 'Košice', 'state' => 'Košický kraj',
            'country' => 'Slovakia', 'postcode' => '040 01', 'countrycode' => 'SK',
        ]];
        yield 'address has no name' => [Suggestions::address(), 'sk', [
            'osm_key' => 'place', 'osm_value' => 'house', 'type' => 'house', 'housenumber' => '12/18', 'street' => 'Hlavná',
            'city' => 'Košice', 'state' => 'Košický kraj', 'country' => 'Slovensko', 'postcode' => '040 01', 'countrycode' => 'SK',
        ]];
        yield 'point of interest has the key of the rules' => [Suggestions::hotel(), 'sk', [
            'osm_type' => 'R', 'osm_id' => 2256517, 'osm_key' => 'tourism', 'osm_value' => 'hotel', 'type' => 'house',
            'name' => 'Hotel Devín', 'city' => 'Bratislava', 'state' => 'Bratislavský kraj', 'country' => 'Slovensko',
            'postcode' => '811 02', 'countrycode' => 'SK',
        ]];
        yield 'country without a translation is left out' => [Suggestions::vienna(), 'sk', [
            'osm_type' => 'R', 'osm_id' => 109166, 'osm_key' => 'place', 'osm_value' => 'city', 'type' => 'city',
            'name' => 'Wien', 'state' => 'Wien', 'countrycode' => 'AT',
        ]];
    }

    public function testPointOfInterestOutsideTheRulesHasNoTag(): void
    {
        $factory = new FeatureFactory(new FakeNameCatalog(), new SelectionRules(pois: ['tourism' => ['hotel']]));

        $feature = $factory->create(Suggestions::station(), null);

        $this->assertNull($feature->osmKey);
        $this->assertNull($feature->osmValue);
        $this->assertSame([], $feature->categories());
    }

    public function testUnknownPlaceTypeIsALocality(): void
    {
        $city = Suggestions::city();
        $farm = new Suggestion($city->id, $city->kind, 'farm', 'Majer', 'Majer, SK', $city->coordinates, 'SK');

        $this->assertSame('locality', (new FeatureFactory(new FakeNameCatalog()))->create($farm, null)->layer->value);
    }
}
