<?php

namespace Tito10047\Routing\Tests\Valhalla;

use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Valhalla\CountryStretch;
use Tito10047\Routing\Valhalla\ManeuverTollLocator;

final class ManeuverTollLocatorTest extends TestCase
{
    public function testFindsNothingWithoutTolledManeuvers(): void
    {
        $leg = Legs::straight(5, 400.0, ['SK']);

        $this->assertSame([], (new ManeuverTollLocator())->locate($leg, [new CountryStretch('SK', 0.0, 400.0)]));
    }

    public function testFindsNothingWithoutCountries(): void
    {
        $leg = Legs::straight(5, 400.0, [], [], [[0, 4]]);

        $this->assertSame([], (new ManeuverTollLocator())->locate($leg, []));
    }

    public function testKeepsAManeuverInsideOneCountry(): void
    {
        $leg = Legs::straight(5, 400.0, ['SK'], [], [[1, 3]]);

        $tolled = (new ManeuverTollLocator())->locate($leg, [new CountryStretch('SK', 0.0, 400.0)]);

        $this->assertSame([['SK', 100.0, 300.0]], $this->describe($tolled));
    }

    public function testCutsAManeuverAtTheBorder(): void
    {
        // the motorway from point 1 to point 9 crosses from SK to HU at 450 m
        $leg = Legs::straight(11, 1000.0, ['SK', 'HU'], [], [[1, 9]]);
        $countries = [new CountryStretch('SK', 0.0, 450.0), new CountryStretch('HU', 450.0, 1000.0)];

        $tolled = (new ManeuverTollLocator())->locate($leg, $countries);

        $this->assertSame([['SK', 100.0, 450.0], ['HU', 450.0, 900.0]], $this->describe($tolled));
    }

    public function testCountsEveryTolledManeuver(): void
    {
        // two maneuvers meeting at point 3, a free road, then a third one in another country
        $leg = Legs::straight(11, 1000.0, ['SK', 'HU'], [], [[0, 3], [3, 5], [7, 10]]);
        $countries = [new CountryStretch('SK', 0.0, 600.0), new CountryStretch('HU', 600.0, 1000.0)];

        $tolled = (new ManeuverTollLocator())->locate($leg, $countries);

        $this->assertSame([['SK', 0.0, 300.0], ['SK', 300.0, 500.0], ['HU', 700.0, 1000.0]], $this->describe($tolled));
    }

    public function testIgnoresAManeuverOfASinglePoint(): void
    {
        $leg = Legs::straight(5, 400.0, ['SK'], [], [[2, 2]]);

        $this->assertSame([], (new ManeuverTollLocator())->locate($leg, [new CountryStretch('SK', 0.0, 400.0)]));
    }

    /**
     * @param list<CountryStretch> $stretches
     *
     * @return list<array{string, float, float}>
     */
    private function describe(array $stretches): array
    {
        return array_map(static fn (CountryStretch $s): array => [$s->country, round($s->fromMeters, 6), round($s->toMeters, 6)], $stretches);
    }
}
