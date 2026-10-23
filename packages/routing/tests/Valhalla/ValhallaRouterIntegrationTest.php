<?php

namespace Tito10047\Routing\Tests\Valhalla;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Exception\RouteNotFoundException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\Tests\Fixtures\Fixture;
use Tito10047\Routing\Valhalla\ValhallaRouter;

/**
 * Asks a live Valhalla, the one at VALHALLA_URL, built from the 9 countries of the research
 * (resources/docker/README.md); skipped when the variable is not set.
 *
 * Tile data changes with every OpenStreetMap extract, so the distances are compared loosely.
 */
#[Group('integration')]
final class ValhallaRouterIntegrationTest extends TestCase
{
    private ValhallaRouter $router;

    protected function setUp(): void
    {
        $url = getenv('VALHALLA_URL');
        if (!\is_string($url) || '' === $url) {
            $this->markTestSkipped('Set VALHALLA_URL to the URL of a Valhalla server to run the integration tests.');
        }

        $this->router = new ValhallaRouter($url);
    }

    public function testFindsARouteWithAlternatives(): void
    {
        $routes = $this->router->route(Fixture::request('ba-kosice-alternatives'));

        $this->assertGreaterThanOrEqual(2, \count($routes));
        $this->assertEqualsWithDelta(441_000, $routes[0]->distanceMeters, 20_000);
        $this->assertNotSame([], $routes[0]->countries);
        $this->assertSame('SK', $routes[0]->countries[0]->country);
    }

    public function testFindsTheCountriesAndTollsThroughFourCountries(): void
    {
        $route = $this->router->route(Fixture::request('ba-split'))[0];

        $this->assertSame(['SK', 'HU', 'SI', 'HU', 'HR'], array_map(static fn (\Tito10047\Routing\CountrySegment $segment): string => $segment->country, $route->countries));
        $expected = ['SK' => 15_051, 'HU' => 162_683, 'SI' => 16_307, 'HR' => 477_321];
        $tolled = $route->tolledByCountry();
        $this->assertSame(array_keys($expected), array_keys($tolled));
        foreach ($expected as $country => $meters) {
            $this->assertEqualsWithDelta($meters, $tolled[$country], 0.05 * $meters, $country);
        }
    }

    public function testFindsNoRouteFromAPedestrianZoneWithoutARadius(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $this->router->route(Fixture::request('stephansplatz-without-radius'));
    }

    public function testFindsARouteFromAPedestrianZoneWithARadius(): void
    {
        $route = $this->router->route(Fixture::request('stephansplatz-with-radius'))[0];

        $this->assertSame(['AT', 'SK'], array_keys($route->distanceByCountry()));
    }

    public function testFindsARouteWithAStop(): void
    {
        $routes = $this->router->route(Fixture::request('multi-stop'));

        $this->assertCount(1, $routes);
        $this->assertSame(['SK', 'AT', 'CZ'], array_keys($routes[0]->distanceByCountry()));
    }

    public function testIsUnavailableForAPointOutsideTheData(): void
    {
        $this->expectException(RoutingUnavailableException::class);
        $this->expectExceptionCode(171);

        $this->router->route(Fixture::request('outside-data'));
    }
}
