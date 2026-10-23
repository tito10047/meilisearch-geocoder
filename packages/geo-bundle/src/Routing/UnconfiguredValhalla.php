<?php

namespace Tito10047\GeoBundle\Routing;

use Tito10047\Routing\Exception\RoutingNotConfiguredException;
use Tito10047\Routing\Matrix;
use Tito10047\Routing\MatrixInterface;
use Tito10047\Routing\MatrixRequest;
use Tito10047\Routing\RouteRequest;
use Tito10047\Routing\RouterInterface;

/**
 * Stands for Valhalla while "geo.routing.url" resolves to an empty value (an empty environment
 * variable in an environment without routing data).
 *
 * The services depending on the routing are still created; only asking for a route, a matrix or
 * the status fails, with RoutingNotConfiguredException: a RoutingUnavailableException, so that
 * applications fall back as when Valhalla is unreachable, which can still be told apart.
 */
final readonly class UnconfiguredValhalla implements RouterInterface, MatrixInterface, RoutingStatusProviderInterface
{
    public const MESSAGE = 'Valhalla is not configured: geo.routing.url is empty.';

    public function route(RouteRequest $request): array
    {
        throw new RoutingNotConfiguredException(self::MESSAGE);
    }

    public function matrix(MatrixRequest $request): Matrix
    {
        throw new RoutingNotConfiguredException(self::MESSAGE);
    }

    public function status(): RoutingStatus
    {
        throw new RoutingNotConfiguredException(self::MESSAGE);
    }
}
