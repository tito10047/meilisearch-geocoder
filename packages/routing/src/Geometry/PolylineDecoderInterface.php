<?php

namespace Tito10047\Routing\Geometry;

use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Exception\InvalidArgumentException;

/**
 * Turns an encoded line into its points.
 */
interface PolylineDecoderInterface
{
    /**
     * @return list<Coordinates> the points in the order of the line, none for an empty string
     *
     * @throws InvalidArgumentException when the string is not a valid encoded line
     */
    public function decode(string $encoded): array;
}
