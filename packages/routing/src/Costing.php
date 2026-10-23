<?php

namespace Tito10047\Routing;

/**
 * How the route is travelled; the values are Valhalla's costing models.
 */
enum Costing: string
{
    case Car = 'auto';
    case Truck = 'truck';
    case Bicycle = 'bicycle';
    case Pedestrian = 'pedestrian';
}
