<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\Exception\RouteNotFoundException;
use Tito10047\Routing\Exception\RoutingUnavailableException;

/**
 * Decides what an unsuccessful answer of Valhalla means for the caller.
 */
interface ErrorTranslatorInterface
{
    /**
     * @param int    $statusCode the HTTP status of the answer, anything but 2xx
     * @param string $body       the body of the answer, Valhalla's JSON error or anything a proxy put there
     *
     * @return RouteNotFoundException|RoutingUnavailableException the exception to throw
     */
    public function translate(int $statusCode, string $body): RouteNotFoundException|RoutingUnavailableException;
}
