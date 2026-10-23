<?php

namespace Tito10047\Routing\Tests\Valhalla;

use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Valhalla\AdminCrossing;
use Tito10047\Routing\Valhalla\Leg;
use Tito10047\Routing\Valhalla\ShapeSpan;

/**
 * Builds legs along the equator with points evenly spaced, so that the point i of a leg of
 * n points and length L lies at i * L / (n - 1) metres.
 */
final class Legs
{
    /**
     * @param list<string>                    $countries the country of each admin
     * @param list<array{int, int, int, int}> $crossings from admin, to admin, begin and end shape index
     * @param list<array{int, int}>           $tolled    begin and end shape index of the tolled maneuvers
     */
    public static function straight(int $points, float $lengthMeters, array $countries = [], array $crossings = [], array $tolled = []): Leg
    {
        return new Leg(
            array_map(static fn (int $i): Coordinates => new Coordinates(0.0, $i / 100), range(0, $points - 1)),
            $lengthMeters,
            $countries,
            array_map(static fn (array $c): AdminCrossing => new AdminCrossing($c[0], $c[1], new ShapeSpan($c[2], $c[3])), $crossings),
            array_map(static fn (array $t): ShapeSpan => new ShapeSpan($t[0], $t[1]), $tolled),
        );
    }
}
