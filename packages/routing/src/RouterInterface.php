<?php

namespace Tito10047\Routing;

use Tito10047\Routing\Exception\RouteNotFoundException;
use Tito10047\Routing\Exception\RoutingUnavailableException;

/**
 * Finds routes through points.
 */
interface RouterInterface
{
    /**
     * Finds the best route through the points of the request and, when asked and possible, alternatives.
     *
     * @return non-empty-list<Route> the best route first
     *
     * @throws RouteNotFoundException      when no route of the requested costing goes through the points
     * @throws RoutingUnavailableException when the engine cannot answer now (or the points are outside its data)
     */
    public function route(RouteRequest $request): array;
}
