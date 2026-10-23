<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\Route;

/**
 * Reads the routes from a successful answer of Valhalla's `/route`.
 */
interface ResponseParserInterface
{
    /**
     * @param string $body the JSON body of a 2xx answer
     *
     * @return non-empty-list<Route> the best route first, then the alternatives
     *
     * @throws RoutingUnavailableException when the body is not a route Valhalla would return
     */
    public function parse(string $body): array;
}
