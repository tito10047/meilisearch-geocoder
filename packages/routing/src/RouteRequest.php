<?php

namespace Tito10047\Routing;

use Tito10047\Routing\Exception\InvalidArgumentException;

/**
 * What a route is asked for: the points to go through, in order, and how.
 */
final readonly class RouteRequest
{
    /**
     * @param list<Coordinates> $points        start, stops, destination — at least two
     * @param int               $alternatives  how many different routes are wanted at most, 1 or more;
     *                                         more than one is honoured between two points only (as by every engine)
     * @param int               $radiusMeters  how far a point may snap to a road, 0 or more; without it a point in a
     *                                         pedestrian zone (Stephansplatz in Vienna) has no route
     * @param bool              $avoidTolls    prefer roads without tolls (a preference: a tolled road is still used
     *                                         when there is no reasonable other way)
     * @param bool              $avoidHighways prefer roads other than motorways (a preference, as $avoidTolls)
     *
     * @throws InvalidArgumentException when a value is out of its range
     */
    public function __construct(
        public array $points,
        public int $alternatives = 1,
        public Costing $costing = Costing::Car,
        public int $radiusMeters = 200,
        public bool $avoidTolls = false,
        public bool $avoidHighways = false,
    ) {
        if (!array_is_list($points)) {
            throw new InvalidArgumentException('The points must be a list.');
        }
        if (\count($points) < 2) {
            throw new InvalidArgumentException(\sprintf('A route needs at least two points, %d given.', \count($points)));
        }
        $this->assertPoints($points);
        if ($alternatives < 1) {
            throw new InvalidArgumentException(\sprintf('The number of alternatives must be at least 1, %d given.', $alternatives));
        }
        if ($radiusMeters < 0) {
            throw new InvalidArgumentException(\sprintf('The radius must not be negative, %d given.', $radiusMeters));
        }
    }

    /**
     * @param array<mixed> $points
     */
    private function assertPoints(array $points): void
    {
        foreach ($points as $index => $point) {
            if (!$point instanceof Coordinates) {
                throw new InvalidArgumentException(\sprintf('The point %d must be an instance of %s, %s given.', $index, Coordinates::class, get_debug_type($point)));
            }
        }
    }
}
