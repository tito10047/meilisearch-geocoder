<?php

namespace Tito10047\Routing;

use Tito10047\Routing\Exception\InvalidArgumentException;

/**
 * A route through all the points of a request, in their order.
 */
final readonly class Route
{
    /**
     * @param int                  $distanceMeters  0 or more
     * @param int                  $durationSeconds 0 or more
     * @param list<Coordinates>    $geometry        the whole line, as the engine returns it
     * @param list<CountrySegment> $countries       in the order driven, a country entered twice listed twice;
     *                                              empty when the engine has no borders in its data
     * @param list<CountrySegment> $tolled          metres on tolled roads, one per country, in the order first entered
     *
     * @throws InvalidArgumentException when a value is out of its range or a list holds something else
     */
    public function __construct(
        public int $distanceMeters,
        public int $durationSeconds,
        public array $geometry,
        public array $countries,
        public array $tolled,
    ) {
        if ($distanceMeters < 0) {
            throw new InvalidArgumentException(\sprintf('The distance must not be negative, %d given.', $distanceMeters));
        }
        if ($durationSeconds < 0) {
            throw new InvalidArgumentException(\sprintf('The duration must not be negative, %d given.', $durationSeconds));
        }
        $this->assertListOf($geometry, Coordinates::class, 'geometry');
        $this->assertListOf($countries, CountrySegment::class, 'countries');
        $this->assertListOf($tolled, CountrySegment::class, 'tolled');

        $seen = [];
        foreach ($tolled as $segment) {
            if (isset($seen[$segment->country])) {
                throw new InvalidArgumentException(\sprintf('The tolled distances must list every country once, "%s" is listed twice.', $segment->country));
            }
            $seen[$segment->country] = true;
        }
    }

    /**
     * Metres driven in each country, in the order they are first entered.
     *
     * @return array<string, int>
     */
    public function distanceByCountry(): array
    {
        $distances = [];
        foreach ($this->countries as $segment) {
            $distances[$segment->country] = ($distances[$segment->country] ?? 0) + $segment->distanceMeters;
        }

        return $distances;
    }

    /**
     * Metres on tolled roads in each country that has any, in the order they are first entered.
     *
     * @return array<string, int>
     */
    public function tolledByCountry(): array
    {
        $distances = [];
        foreach ($this->tolled as $segment) {
            $distances[$segment->country] = $segment->distanceMeters;
        }

        return $distances;
    }

    /**
     * @param array<mixed> $values
     * @param class-string $class
     */
    private function assertListOf(array $values, string $class, string $name): void
    {
        if (!array_is_list($values)) {
            throw new InvalidArgumentException(\sprintf('The %s must be a list.', $name));
        }
        foreach ($values as $index => $value) {
            if (!$value instanceof $class) {
                throw new InvalidArgumentException(\sprintf('The %s item %d must be an instance of %s, %s given.', $name, $index, $class, get_debug_type($value)));
            }
        }
    }
}
