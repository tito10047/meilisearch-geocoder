<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\Exception\InvalidArgumentException;

/**
 * Where a leg passes from one administrative area to another, as Valhalla reports it in
 * `admin_crossings`: the border lies on the edge between the two shape indexes.
 */
final readonly class AdminCrossing
{
    /**
     * @param int $fromAdminIndex index into the leg's admins of the area left
     * @param int $toAdminIndex   index into the leg's admins of the area entered
     *
     * @throws InvalidArgumentException when an index is negative or the edge goes backwards
     */
    public function __construct(
        public int $fromAdminIndex,
        public int $toAdminIndex,
        public ShapeSpan $edge,
    ) {
        if ($fromAdminIndex < 0 || $toAdminIndex < 0) {
            throw new InvalidArgumentException(\sprintf('An admin index must not be negative, %d and %d given.', $fromAdminIndex, $toAdminIndex));
        }
    }
}
