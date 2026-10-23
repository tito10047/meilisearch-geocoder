<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Costing;

/**
 * The parts of a Valhalla request shared by `/route` and `/sources_to_targets`.
 *
 * @internal
 */
final class RequestParts
{
    /**
     * A location with its snapping radius (Valhalla's default 0 leaves a point in a pedestrian
     * zone without a route).
     *
     * @return array{lat: float, lon: float, radius: int}
     */
    public static function location(Coordinates $point, int $radiusMeters): array
    {
        return ['lat' => $point->latitude, 'lon' => $point->longitude, 'radius' => $radiusMeters];
    }

    /**
     * `costing` and, when something is avoided, `costing_options`: avoiding tolls and motorways
     * sets `use_tolls` / `use_highways` to 0, which Valhalla takes as a strong preference, not a
     * ban. Costings without these options (bicycle, pedestrian) ignore them.
     *
     * @return array{costing: string, costing_options?: array<string, array<string, int>>}
     */
    public static function costing(Costing $costing, bool $avoidTolls, bool $avoidHighways): array
    {
        $body = ['costing' => $costing->value];

        $options = [];
        if ($avoidTolls) {
            $options['use_tolls'] = 0;
        }
        if ($avoidHighways) {
            $options['use_highways'] = 0;
        }
        if ([] !== $options) {
            $body['costing_options'] = [$costing->value => $options];
        }

        return $body;
    }
}
