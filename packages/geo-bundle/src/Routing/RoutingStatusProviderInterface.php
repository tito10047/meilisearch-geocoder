<?php

namespace Tito10047\GeoBundle\Routing;

use Tito10047\Routing\Exception\RoutingUnavailableException;

/**
 * Tells whether the routing engine is reachable, and what it runs.
 */
interface RoutingStatusProviderInterface
{
    /**
     * Asks the engine for its status.
     *
     * @throws RoutingUnavailableException when the engine cannot be reached or answers unexpectedly
     */
    public function status(): RoutingStatus;
}
