<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\Coordinates;
use Tito10047\Routing\RouteRequest;

/**
 * Encodes a route request for Valhalla's `/route`.
 *
 * - every point gets the request's `radius` (Valhalla's default 0 leaves a point in a pedestrian
 *   zone without a route);
 * - `alternates` (the number of routes besides the best one) only between two points: Valhalla
 *   computes alternatives between two points only and ignores the parameter with stops;
 * - `directions_type: maneuvers`: the maneuvers carry the tolls, the instruction texts are not needed;
 * - `admin_crossings: true` for the countries, `shape_format: polyline6` for the geometry;
 * - avoiding tolls and motorways sets `use_tolls` / `use_highways` to 0 in the costing options:
 *   Valhalla then avoids them as far as it reasonably can, it does not forbid them. Costings
 *   without these options (bicycle, pedestrian) ignore them.
 *
 * @see https://valhalla.github.io/valhalla/api/turn-by-turn/api-reference/
 */
final class RequestEncoder implements RequestEncoderInterface
{
    public function encode(RouteRequest $request): string
    {
        $body = [
            'locations' => array_map(static fn (Coordinates $point): array => RequestParts::location($point, $request->radiusMeters), $request->points),
        ] + RequestParts::costing($request->costing, $request->avoidTolls, $request->avoidHighways);

        if (2 === \count($request->points) && $request->alternatives > 1) {
            $body['alternates'] = $request->alternatives - 1;
        }

        $body += [
            'units' => 'kilometers',
            'directions_type' => 'maneuvers',
            'admin_crossings' => true,
            'shape_format' => 'polyline6',
        ];

        return json_encode($body, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
    }
}
