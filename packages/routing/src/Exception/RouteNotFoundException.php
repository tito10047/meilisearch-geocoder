<?php

namespace Tito10047\Routing\Exception;

/**
 * Thrown when the routing engine answered, but no route of the requested costing goes through
 * the points (a point on an island, in a pedestrian zone without a snapping radius, …).
 *
 * Asking another engine is unlikely to help, unlike with {@see RoutingUnavailableException}.
 */
final class RouteNotFoundException extends RuntimeException
{
}
