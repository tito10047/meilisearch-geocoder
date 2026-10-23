<?php

namespace Tito10047\Routing;

/**
 * One cell of a {@see Matrix}: the route from a source to a target, if there is one.
 */
final readonly class MatrixEntry
{
    /**
     * @param int      $source          the index of the source in the request
     * @param int      $target          the index of the target in the request
     * @param int|null $distanceMeters  null when the target cannot be reached from the source
     * @param int|null $durationSeconds null exactly when the distance is
     */
    public function __construct(
        public int $source,
        public int $target,
        public ?int $distanceMeters,
        public ?int $durationSeconds,
    ) {
    }

    public function isReachable(): bool
    {
        return null !== $this->distanceMeters;
    }
}
