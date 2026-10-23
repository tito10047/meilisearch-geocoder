<?php

namespace Tito10047\Routing;

use Tito10047\Routing\Exception\InvalidArgumentException;

/**
 * What a distance matrix is asked for: the distance and duration from every source to every
 * target, each pair routed on its own.
 *
 * Engines limit the size of a matrix (Valhalla to 2,500 pairs by default); the matrix
 * implementation checks its own limit, see {@see MatrixInterface}.
 */
final readonly class MatrixRequest
{
    /**
     * @param list<Coordinates> $sources       where the routes start, at least one
     * @param list<Coordinates> $targets       where the routes end, at least one
     * @param int               $radiusMeters  how far a point may snap to a road, 0 or more (as in {@see RouteRequest})
     * @param bool              $avoidTolls    prefer roads without tolls (a preference, as in {@see RouteRequest})
     * @param bool              $avoidHighways prefer roads other than motorways (a preference, as in {@see RouteRequest})
     *
     * @throws InvalidArgumentException when a value is out of its range
     */
    public function __construct(
        public array $sources,
        public array $targets,
        public Costing $costing = Costing::Car,
        public int $radiusMeters = 200,
        public bool $avoidTolls = false,
        public bool $avoidHighways = false,
    ) {
        $this->assertPoints($sources, 'source');
        $this->assertPoints($targets, 'target');
        if ($radiusMeters < 0) {
            throw new InvalidArgumentException(\sprintf('The radius must not be negative, %d given.', $radiusMeters));
        }
    }

    /**
     * The number of source-target pairs, the size of the matrix.
     */
    public function pairs(): int
    {
        return \count($this->sources) * \count($this->targets);
    }

    /**
     * @param array<mixed> $points
     */
    private function assertPoints(array $points, string $name): void
    {
        if (!array_is_list($points)) {
            throw new InvalidArgumentException(\sprintf('The %ss must be a list.', $name));
        }
        if ([] === $points) {
            throw new InvalidArgumentException(\sprintf('A matrix needs at least one %s.', $name));
        }
        foreach ($points as $index => $point) {
            if (!$point instanceof Coordinates) {
                throw new InvalidArgumentException(\sprintf('The %s %d must be an instance of %s, %s given.', $name, $index, Coordinates::class, get_debug_type($point)));
            }
        }
    }
}
