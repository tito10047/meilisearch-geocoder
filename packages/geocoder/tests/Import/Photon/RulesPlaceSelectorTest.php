<?php

namespace Tito10047\Geocoder\Tests\Import\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Import\Photon\RulesPlaceSelector;
use Tito10047\Geocoder\Import\SelectionRules;
use Tito10047\Geocoder\Tests\Fake\Places;

final class RulesPlaceSelectorTest extends TestCase
{
    /**
     * @param array<string, string> $names
     */
    #[DataProvider('providePlaces')]
    public function testSelects(string $osmKey, string $osmValue, array $names, ?DocumentKind $expected): void
    {
        $selector = new RulesPlaceSelector(new SelectionRules());

        $this->assertSame($expected, $selector->select(Places::create(osmKey: $osmKey, osmValue: $osmValue, names: $names)));
    }

    /**
     * @return iterable<string, array{string, string, array<string, string>, DocumentKind|null}>
     */
    public static function providePlaces(): iterable
    {
        $named = ['name' => 'Viničky'];

        yield 'village' => ['place', 'village', $named, DocumentKind::Place];
        yield 'city' => ['place', 'city', $named, DocumentKind::Place];
        yield 'locality' => ['place', 'locality', $named, null];
        yield 'residential street' => ['highway', 'residential', $named, DocumentKind::Street];
        yield 'service road' => ['highway', 'service', $named, DocumentKind::Street];
        yield 'road under construction' => ['highway', 'construction', $named, DocumentKind::Street];
        // things along a road are no streets (the prototype took every named highway)
        yield 'bus stop' => ['highway', 'bus_stop', $named, null];
        yield 'platform' => ['highway', 'platform', $named, null];
        yield 'motorway junction' => ['highway', 'motorway_junction', $named, null];
        yield 'rest area' => ['highway', 'rest_area', $named, null];
        yield 'proposed road' => ['highway', 'proposed', $named, null];
        yield 'hotel' => ['tourism', 'hotel', $named, DocumentKind::Poi];
        yield 'castle' => ['historic', 'castle', $named, DocumentKind::Poi];
        yield 'restaurant' => ['amenity', 'restaurant', $named, null];
        yield 'peak' => ['natural', 'peak', $named, null];
        yield 'nameless village' => ['place', 'village', ['name:hu' => 'Szőlőske'], null];
        yield 'nameless street' => ['highway', 'residential', [], null];
        yield 'nameless hotel' => ['tourism', 'hotel', ['name' => ''], null];
    }

    public function testFollowsTheRules(): void
    {
        $selector = new RulesPlaceSelector(new SelectionRules(placeTypes: ['locality' => 10], pois: ['amenity' => ['restaurant']]));

        $this->assertSame(DocumentKind::Place, $selector->select(Places::create(osmValue: 'locality')));
        $this->assertNull($selector->select(Places::create(osmValue: 'village')));
        $this->assertSame(DocumentKind::Poi, $selector->select(Places::create(osmKey: 'amenity', osmValue: 'restaurant')));
        $this->assertNull($selector->select(Places::create(osmKey: 'tourism', osmValue: 'hotel')));
    }

    public function testFollowsTheExcludedHighways(): void
    {
        $selector = new RulesPlaceSelector(new SelectionRules(streetExcludedHighways: ['track']));

        $this->assertNull($selector->select(Places::create(osmKey: 'highway', osmValue: 'track')));
        $this->assertSame(DocumentKind::Street, $selector->select(Places::create(osmKey: 'highway', osmValue: 'bus_stop')));
    }

    public function testAnExcludedHighwayMayBeAPointOfInterest(): void
    {
        $selector = new RulesPlaceSelector(new SelectionRules(pois: ['highway' => ['services']]));

        $this->assertSame(DocumentKind::Poi, $selector->select(Places::create(osmKey: 'highway', osmValue: 'services')));
        $this->assertNull($selector->select(Places::create(osmKey: 'highway', osmValue: 'bus_stop')));
    }
}
