<?php

namespace Tito10047\Routing;

use Tito10047\Routing\Exception\InvalidArgumentException;

/**
 * The distances and durations from every source to every target of a {@see MatrixRequest},
 * indexed as the sources and targets of the request.
 *
 * A pair without a route has neither a distance nor a duration (null): a target the source
 * cannot reach, or, with Valhalla, a route longer than the engine's matrix limit.
 */
final readonly class Matrix
{
    /**
     * @param list<list<int|null>> $distances metres, one row per source, one column per target
     * @param list<list<int|null>> $durations seconds, the same shape, null exactly where the distance is
     *
     * @throws InvalidArgumentException when the lists are not rectangular, differ in shape or nulls, or hold
     *                                  negative or other values
     */
    public function __construct(
        private array $distances,
        private array $durations,
    ) {
        if (!array_is_list($distances) || [] === $distances || !array_is_list($durations)) {
            throw new InvalidArgumentException('The distances and durations must be non-empty lists of rows.');
        }
        if (\count($durations) !== \count($distances)) {
            throw new InvalidArgumentException(\sprintf('The distances have %d row(s), the durations %d.', \count($distances), \count($durations)));
        }
        $targets = null;
        foreach ($distances as $source => $row) {
            $durationRow = $durations[$source];
            if (!\is_array($row) || !array_is_list($row) || [] === $row || !\is_array($durationRow) || !array_is_list($durationRow)) {
                throw new InvalidArgumentException(\sprintf('The row %d must be a non-empty list.', $source));
            }
            $targets ??= \count($row);
            if (\count($row) !== $targets || \count($durationRow) !== $targets) {
                throw new InvalidArgumentException(\sprintf('Every row must have %d target(s), the row %d has %d distance(s) and %d duration(s).', $targets, $source, \count($row), \count($durationRow)));
            }
            foreach ($row as $target => $distance) {
                $duration = $durationRow[$target];
                $this->assertValue($distance, 'distance', $source, $target);
                $this->assertValue($duration, 'duration', $source, $target);
                if ((null === $distance) !== (null === $duration)) {
                    throw new InvalidArgumentException(\sprintf('The distance and the duration from %d to %d must both be null or neither.', $source, $target));
                }
            }
        }
    }

    public function sourceCount(): int
    {
        return \count($this->distances);
    }

    public function targetCount(): int
    {
        return \count($this->distances[0]);
    }

    /**
     * @return int|null metres, null when there is no route between the pair
     *
     * @throws InvalidArgumentException when an index is outside the matrix
     */
    public function distanceMeters(int $source, int $target): ?int
    {
        $this->assertIndexes($source, $target);

        return $this->distances[$source][$target];
    }

    /**
     * @return int|null seconds, null when there is no route between the pair
     *
     * @throws InvalidArgumentException when an index is outside the matrix
     */
    public function durationSeconds(int $source, int $target): ?int
    {
        $this->assertIndexes($source, $target);

        return $this->durations[$source][$target];
    }

    /**
     * @throws InvalidArgumentException when an index is outside the matrix
     */
    public function isReachable(int $source, int $target): bool
    {
        return null !== $this->distanceMeters($source, $target);
    }

    /**
     * Every pair, the row of the first source first.
     *
     * @return \Generator<int, MatrixEntry>
     */
    public function entries(): \Generator
    {
        foreach ($this->distances as $source => $row) {
            foreach ($row as $target => $distance) {
                yield new MatrixEntry($source, $target, $distance, $this->durations[$source][$target]);
            }
        }
    }

    /**
     * The target reached soonest from the source (the first of equally distant ones).
     *
     * @return MatrixEntry|null null when the source reaches no target
     *
     * @throws InvalidArgumentException when the index is outside the matrix
     */
    public function nearestTarget(int $source): ?MatrixEntry
    {
        $this->assertIndexes($source, 0);

        $nearest = null;
        foreach ($this->durations[$source] as $target => $duration) {
            if (null !== $duration && (null === $nearest || $duration < $this->durations[$source][$nearest])) {
                $nearest = $target;
            }
        }

        return null === $nearest ? null : new MatrixEntry($source, $nearest, $this->distances[$source][$nearest], $this->durations[$source][$nearest]);
    }

    private function assertValue(mixed $value, string $name, int $source, int $target): void
    {
        if (null !== $value && (!\is_int($value) || $value < 0)) {
            throw new InvalidArgumentException(\sprintf('The %s from %d to %d must be null or an integer of 0 or more, %s given.', $name, $source, $target, get_debug_type($value)));
        }
    }

    private function assertIndexes(int $source, int $target): void
    {
        if ($source < 0 || $source >= $this->sourceCount()) {
            throw new InvalidArgumentException(\sprintf('The source must be between 0 and %d, %d given.', $this->sourceCount() - 1, $source));
        }
        if ($target < 0 || $target >= $this->targetCount()) {
            throw new InvalidArgumentException(\sprintf('The target must be between 0 and %d, %d given.', $this->targetCount() - 1, $target));
        }
    }
}
