<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\Coordinates;
use Tito10047\Routing\MatrixRequest;

/**
 * Encodes a matrix request for Valhalla's `/sources_to_targets`.
 *
 * - every source and target gets the request's `radius`; the costing and the avoided roads are
 *   encoded as for `/route` (see {@see RequestEncoder});
 * - `verbose: false`: the concise answer, two nested arrays (`durations`, `distances`) with one
 *   row per source, instead of an object per pair with the snapped coordinates;
 * - `units: kilometers` (Valhalla's default, set explicitly as the parser relies on it).
 *
 * @see https://valhalla.github.io/valhalla/api/matrix/
 */
final class MatrixRequestEncoder implements MatrixRequestEncoderInterface
{
    public function encode(MatrixRequest $request): string
    {
        $location = static fn (Coordinates $point): array => RequestParts::location($point, $request->radiusMeters);
        $body = [
            'sources' => array_map($location, $request->sources),
            'targets' => array_map($location, $request->targets),
        ] + RequestParts::costing($request->costing, $request->avoidTolls, $request->avoidHighways) + [
            'verbose' => false,
            'units' => 'kilometers',
        ];

        return json_encode($body, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
    }
}
