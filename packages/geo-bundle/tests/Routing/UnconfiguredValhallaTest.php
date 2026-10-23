<?php

namespace Tito10047\GeoBundle\Tests\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\GeoBundle\Routing\UnconfiguredValhalla;
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\MatrixRequest;
use Tito10047\Routing\RouteRequest;

final class UnconfiguredValhallaTest extends TestCase
{
    /**
     * @return iterable<string, array{\Closure(UnconfiguredValhalla): mixed}>
     */
    public static function calls(): iterable
    {
        $points = [new Coordinates(48.1486, 17.1077), new Coordinates(48.7164, 21.2611)];

        yield 'route' => [static fn (UnconfiguredValhalla $valhalla): array => $valhalla->route(new RouteRequest($points))];
        yield 'matrix' => [static fn (UnconfiguredValhalla $valhalla): mixed => $valhalla->matrix(new MatrixRequest($points, $points))];
        yield 'status' => [static fn (UnconfiguredValhalla $valhalla): mixed => $valhalla->status()];
    }

    /**
     * @param \Closure(UnconfiguredValhalla): mixed $call
     */
    #[DataProvider('calls')]
    public function testEveryCallIsUnavailable(\Closure $call): void
    {
        $this->expectException(RoutingUnavailableException::class);
        $this->expectExceptionMessage('Valhalla is not configured: geo.routing.url is empty.');

        $call(new UnconfiguredValhalla());
    }
}
