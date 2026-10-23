<?php

namespace Tito10047\Routing\Tests\Valhalla;

use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Valhalla\AdminCrossingCountryLocator;
use Tito10047\Routing\Valhalla\CountryStretch;

final class AdminCrossingCountryLocatorTest extends TestCase
{
    public function testFindsNothingWithoutAdmins(): void
    {
        $this->assertSame([], (new AdminCrossingCountryLocator())->locate(Legs::straight(3, 200.0)));
    }

    public function testPutsALegWithoutCrossingsInItsOnlyCountry(): void
    {
        $stretches = (new AdminCrossingCountryLocator())->locate(Legs::straight(3, 200.0, ['SK']));

        $this->assertSame([['SK', 0.0, 200.0]], $this->describe($stretches));
    }

    public function testPlacesTheBorderInTheMiddleOfTheCrossingEdge(): void
    {
        // points every 100 m, the border on the edge from point 2 to point 4
        $leg = Legs::straight(7, 600.0, ['SK', 'HU'], [[0, 1, 2, 4]]);

        $stretches = (new AdminCrossingCountryLocator())->locate($leg);

        $this->assertSame([['SK', 0.0, 300.0], ['HU', 300.0, 600.0]], $this->describe($stretches));
    }

    public function testListsACountryEnteredTwiceTwice(): void
    {
        // SK, HU, SI, back to HU, HR: admins as Valhalla lists them, every one once
        $leg = Legs::straight(11, 1000.0, ['SK', 'HU', 'SI', 'HR'], [[0, 1, 1, 2], [1, 2, 4, 5], [2, 1, 6, 7], [1, 3, 8, 9]]);

        $stretches = (new AdminCrossingCountryLocator())->locate($leg);

        $this->assertSame([
            ['SK', 0.0, 150.0],
            ['HU', 150.0, 450.0],
            ['SI', 450.0, 650.0],
            ['HU', 650.0, 850.0],
            ['HR', 850.0, 1000.0],
        ], $this->describe($stretches));
    }

    public function testJoinsTheRegionsOfOneCountry(): void
    {
        // Croatian counties are admins of their own
        $leg = Legs::straight(5, 400.0, ['HU', 'HR', 'HR', 'HR'], [[0, 1, 0, 1], [1, 2, 1, 2], [2, 3, 2, 3]]);

        $stretches = (new AdminCrossingCountryLocator())->locate($leg);

        $this->assertSame([['HU', 0.0, 50.0], ['HR', 50.0, 400.0]], $this->describe($stretches));
    }

    public function testLeavesOutAStretchOfAnUnknownCountry(): void
    {
        // a ferry through waters of no country: HR, unknown, HR stays two stretches
        $leg = Legs::straight(5, 400.0, ['HR', '', 'HR'], [[0, 1, 0, 2], [1, 2, 2, 4]]);

        $stretches = (new AdminCrossingCountryLocator())->locate($leg);

        $this->assertSame([['HR', 0.0, 100.0], ['HR', 300.0, 400.0]], $this->describe($stretches));
    }

    public function testIgnoresACrossingThatGoesBackwards(): void
    {
        $leg = Legs::straight(5, 400.0, ['SK', 'AT', 'CZ'], [[0, 1, 2, 3], [1, 2, 1, 2]]);

        $stretches = (new AdminCrossingCountryLocator())->locate($leg);

        $this->assertSame([['SK', 0.0, 250.0], ['CZ', 250.0, 400.0]], $this->describe($stretches));
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
