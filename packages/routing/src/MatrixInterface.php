<?php

namespace Tito10047\Routing;

use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Exception\RoutingUnavailableException;

/**
 * Computes the distances and durations between sets of points (a distance matrix).
 *
 * A pair without a route is null in the matrix; no exception says that a route does not exist.
 */
interface MatrixInterface
{
    /**
     * @throws InvalidArgumentException    when the matrix is larger than the implementation accepts
     * @throws RoutingUnavailableException when the engine cannot answer now, a point is outside its data, or the
     *                                     points are farther apart than it accepts
     */
    public function matrix(MatrixRequest $request): Matrix;
}
