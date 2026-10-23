<?php

namespace Tito10047\Geocoder\Tests\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Photon\Feature;
use Tito10047\Geocoder\Photon\FeatureFactory;
use Tito10047\Geocoder\Photon\Layer;
use Tito10047\Geocoder\Search\Suggestion;
use Tito10047\Geocoder\Tests\Fake\FakeNameCatalog;
use Tito10047\Geocoder\Tests\Fake\Suggestions;

final class FeatureTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../Fixtures/photon-api/';

    public function testLeavesTheUnknownPropertiesOut(): void
    {
        $feature = new Feature(new Coordinates(48.1, 17.1), Layer::Street, 'SK', name: 'Hlavná', city: '');

        $this->assertSame([
            'type' => 'Feature',
            'properties' => ['type' => 'street', 'name' => 'Hlavná', 'countrycode' => 'SK'],
            'geometry' => ['type' => 'Point', 'coordinates' => [17.1, 48.1]],
        ], $feature->jsonSerialize());
    }

    public function testCategoriesAreTheMainTag(): void
    {
        $this->assertSame([['osm', 'tourism', 'hotel']], (new Feature(new Coordinates(0, 0), Layer::House, 'SK', osmKey: 'tourism', osmValue: 'hotel'))->categories());
        $this->assertSame([['osm', 'highway']], (new Feature(new Coordinates(0, 0), Layer::Street, 'SK', osmKey: 'highway'))->categories());
        $this->assertSame([], (new Feature(new Coordinates(0, 0), Layer::House, 'SK'))->categories());
    }

    public function testRejectsAnInvalidOsmType(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Feature(new Coordinates(0, 0), Layer::House, 'SK', osmType: 'X');
    }

    public function testRejectsAnInvalidCountryCode(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Feature(new Coordinates(0, 0), Layer::House, 'sk');
    }

    /**
     * The shape of a feature is the shape of Photon 1.3 (responses captured from Photon on the
     * data of Slovakia): the same properties in the same order, of the same JSON types, minus
     * those the index does not know.
     */
    #[DataProvider('provideFixtures')]
    public function testHasTheShapeOfPhoton(string $fixture, int $index, Suggestion $suggestion, ?string $language, bool $sameTag = true): void
    {
        $photon = json_decode((string) file_get_contents(self::FIXTURES.$fixture), true, flags: \JSON_THROW_ON_ERROR);
        $expected = $photon['features'][$index];
        $factory = new FeatureFactory(new FakeNameCatalog(['SK|country|SK|sk' => 'Slovensko', 'SK|country|SK|en' => 'Slovakia']));

        $actual = json_decode((string) json_encode($factory->create($suggestion, $language)), true, flags: \JSON_THROW_ON_ERROR);

        $this->assertSame(array_keys($expected), array_keys($actual));
        $this->assertSame($expected['type'], $actual['type']);
        $this->assertSame($expected['geometry']['type'], $actual['geometry']['type']);
        $this->assertCount(2, $actual['geometry']['coordinates']);
        $properties = array_intersect_key($expected['properties'], $actual['properties']);
        $this->assertSame(array_keys($properties), array_keys($actual['properties']), 'the properties are those of Photon, in its order');
        foreach ($actual['properties'] as $name => $value) {
            $this->assertSame(get_debug_type($expected['properties'][$name]), get_debug_type($value), $name);
        }
        // the classification is Photon's (the names depend on the data, not on the mapping)
        foreach ($sameTag ? ['osm_key', 'osm_value', 'type', 'countrycode'] : ['type', 'countrycode'] as $name) {
            if (\array_key_exists($name, $actual['properties'])) {
                $this->assertSame($expected['properties'][$name], $actual['properties'][$name], $name);
            }
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: int, 2: Suggestion, 3: string|null, 4?: bool}>
     */
    public static function provideFixtures(): iterable
    {
        yield 'city' => ['city.json', 0, Suggestions::city(), 'sk'];
        yield 'street' => ['street-and-poi.json', 0, self::street('Nižný Klátov', '044 12'), 'sk'];
        yield 'point of interest in English' => ['poi-en.json', 0, Suggestions::hotel(), 'en'];
        // Photon tags the house of the address ("building"/"apartments"), the index knows its number only ("place"/"house")
        yield 'address' => ['house.json', 0, Suggestions::address(), 'sk', false];
    }

    private static function street(string $city, string $postcode): Suggestion
    {
        $street = Suggestions::street();

        return new Suggestion($street->id, $street->kind, $street->type, $street->name, $street->label, $street->coordinates,
            'SK', $city, $street->region, $postcode);
    }
}
