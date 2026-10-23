<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\Exception\InvalidArgumentException;

/**
 * A part of a leg between two indexes of its shape, both included: a maneuver on a tolled road.
 */
final readonly class ShapeSpan
{
    /**
     * @throws InvalidArgumentException when an index is negative or the span goes backwards
     */
    public function __construct(
        public int $beginShapeIndex,
        public int $endShapeIndex,
    ) {
        if ($beginShapeIndex < 0) {
            throw new InvalidArgumentException(\sprintf('A shape index must not be negative, %d given.', $beginShapeIndex));
        }
        if ($endShapeIndex < $beginShapeIndex) {
            throw new InvalidArgumentException(\sprintf('A shape span must not end (%d) before it begins (%d).', $endShapeIndex, $beginShapeIndex));
        }
    }
}
