<?php

namespace Tito10047\Routing\Valhalla;

/**
 * Cuts the maneuvers Valhalla marks with `toll` at the borders of the countries.
 *
 * A maneuver may cross a border (a motorway rarely changes its name there), so it is not counted
 * whole in the country it starts in: its shape indexes give its stretch of the leg, and that
 * stretch is intersected with the stretch of every country. Precise to a point of the shape,
 * without asking Valhalla again (`trace_attributes`).
 */
final class ManeuverTollLocator implements TollLocatorInterface
{
    public function locate(Leg $leg, array $countries): array
    {
        $tolled = [];
        foreach ($leg->tolled as $span) {
            $from = $leg->positionOf($span->beginShapeIndex);
            $to = $leg->positionOf($span->endShapeIndex);
            foreach ($countries as $country) {
                $overlapFrom = max($from, $country->fromMeters);
                $overlapTo = min($to, $country->toMeters);
                if ($overlapTo > $overlapFrom) {
                    $tolled[] = new CountryStretch($country->country, $overlapFrom, $overlapTo);
                }
            }
        }

        return $tolled;
    }
}
