<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Exception\InvalidArgumentException;

/**
 * One leg of a Valhalla trip (from a point of the request to the next one), with what the
 * countries and tolls are computed from.
 *
 * Every point of the shape gets its distance from the start of the leg: the great-circle
 * distances along the shape, scaled so that the whole shape measures what Valhalla reports for
 * the leg. The distances per country then add up to the length of the route.
 */
final readonly class Leg
{
    private const EARTH_RADIUS_METERS = 6_371_008.8;

    /**
     * @var list<float> distance of each point of the shape from the start of the leg
     */
    private array $positions;

    /**
     * @param list<Coordinates>   $shape        the points of the leg, at least two
     * @param float               $lengthMeters the length of the leg reported by Valhalla
     * @param list<string>        $countries    the ISO 3166-1 alpha-2 country of each admin of the leg (Valhalla's
     *                                          `summary.admins`), an empty string where Valhalla does not know it
     * @param list<AdminCrossing> $crossings    in the order of the leg
     * @param list<ShapeSpan>     $tolled       the maneuvers on tolled roads, in the order of the leg
     *
     * @throws InvalidArgumentException when an index points outside the shape or the admins
     */
    public function __construct(
        public array $shape,
        public float $lengthMeters,
        public array $countries,
        public array $crossings,
        public array $tolled,
    ) {
        if (!array_is_list($shape)) {
            throw new InvalidArgumentException('The shape of a leg must be a list.');
        }
        if (\count($shape) < 2) {
            throw new InvalidArgumentException(\sprintf('The shape of a leg needs at least two points, %d given.', \count($shape)));
        }
        $this->assertPoints($shape);
        if (!is_finite($lengthMeters) || $lengthMeters < 0.0) {
            throw new InvalidArgumentException(\sprintf('The length of a leg must not be negative, %F given.', $lengthMeters));
        }
        foreach ($countries as $country) {
            if ('' !== $country && 1 !== preg_match('/^[A-Z]{2}$/D', $country)) {
                throw new InvalidArgumentException(\sprintf('The country of an admin must be an ISO 3166-1 alpha-2 code in upper case or empty, "%s" given.', $country));
            }
        }
        $lastIndex = \count($shape) - 1;
        foreach ($crossings as $crossing) {
            if ($crossing->fromAdminIndex >= \count($countries) || $crossing->toAdminIndex >= \count($countries)) {
                throw new InvalidArgumentException(\sprintf('An admin crossing points to the admins %d and %d, the leg has %d.', $crossing->fromAdminIndex, $crossing->toAdminIndex, \count($countries)));
            }
            $this->assertWithin($crossing->edge, $lastIndex);
        }
        foreach ($tolled as $span) {
            $this->assertWithin($span, $lastIndex);
        }

        $this->positions = $this->measure($shape, $lengthMeters);
    }

    /**
     * The distance of a point of the shape from the start of the leg.
     *
     * @throws InvalidArgumentException when the index is outside the shape
     */
    public function positionOf(int $shapeIndex): float
    {
        if (!isset($this->positions[$shapeIndex])) {
            throw new InvalidArgumentException(\sprintf('The shape index %d is outside the shape of %d points.', $shapeIndex, \count($this->positions)));
        }

        return $this->positions[$shapeIndex];
    }

    private function assertWithin(ShapeSpan $span, int $lastIndex): void
    {
        if ($span->endShapeIndex > $lastIndex) {
            throw new InvalidArgumentException(\sprintf('The shape index %d is outside the shape of %d points.', $span->endShapeIndex, $lastIndex + 1));
        }
    }

    /**
     * @param list<Coordinates> $shape
     *
     * @return list<float>
     */
    private function measure(array $shape, float $lengthMeters): array
    {
        $cumulative = [0.0];
        $total = 0.0;
        for ($i = 1, $count = \count($shape); $i < $count; ++$i) {
            $total += $this->distance($shape[$i - 1], $shape[$i]);
            $cumulative[] = $total;
        }

        // a degenerate shape (all points equal) cannot be scaled: spread the length evenly
        if ($total <= 0.0) {
            $step = $lengthMeters / (\count($shape) - 1);

            return array_map(static fn (int $i): float => $i * $step, array_keys($shape));
        }

        $scale = $lengthMeters / $total;

        return array_map(static fn (float $meters): float => $meters * $scale, $cumulative);
    }

    private function distance(Coordinates $a, Coordinates $b): float
    {
        $latitudeA = deg2rad($a->latitude);
        $latitudeB = deg2rad($b->latitude);
        $sinLatitude = sin(($latitudeB - $latitudeA) / 2);
        $sinLongitude = sin(deg2rad($b->longitude - $a->longitude) / 2);
        $h = $sinLatitude ** 2 + cos($latitudeA) * cos($latitudeB) * $sinLongitude ** 2;

        return 2 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($h)));
    }

    /**
     * @param array<mixed> $points
     */
    private function assertPoints(array $points): void
    {
        foreach ($points as $index => $point) {
            if (!$point instanceof Coordinates) {
                throw new InvalidArgumentException(\sprintf('The point %d of the shape must be an instance of %s, %s given.', $index, Coordinates::class, get_debug_type($point)));
            }
        }
    }
}
