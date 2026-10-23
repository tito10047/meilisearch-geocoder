<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\RouteRequest;

/**
 * Turns a route request into the JSON body of Valhalla's `/route`.
 *
 * The body must ask for what a {@see ResponseParserInterface} reads: the shape, the admin
 * crossings and the maneuvers with their tolls.
 */
interface RequestEncoderInterface
{
    /**
     * @return string the JSON body
     */
    public function encode(RouteRequest $request): string;
}
