<?php

namespace Tito10047\Routing\Valhalla;

/**
 * Splits a leg into countries at Valhalla's `admin_crossings`.
 *
 * Valhalla reports a crossing by the edge on which the border lies, not by the point where it
 * does; the border is placed in the middle of that edge (edges crossing a border are short,
 * usually a few hundred metres). Crossings between two regions of the same country (Croatian
 * counties) join into one stretch. A stretch whose country Valhalla does not know (an empty
 * `country_code`) is left out, so the countries may then add up to less than the leg.
 */
final class AdminCrossingCountryLocator implements CountryLocatorInterface
{
    public function locate(Leg $leg): array
    {
        if ([] === $leg->countries) {
            return [];
        }

        $end = $leg->positionOf(\count($leg->shape) - 1);
        $stretches = [];
        $from = 0.0;
        $admin = 0;
        foreach ($leg->crossings as $crossing) {
            // a border never lies before the previous one, even if Valhalla's crossings overlap
            $border = max($from, ($leg->positionOf($crossing->edge->beginShapeIndex) + $leg->positionOf($crossing->edge->endShapeIndex)) / 2);
            $this->append($stretches, $leg->countries[$crossing->fromAdminIndex], $from, $border);
            $from = $border;
            $admin = $crossing->toAdminIndex;
        }
        $this->append($stretches, $leg->countries[$admin], $from, $end);

        return $stretches;
    }

    /**
     * @param list<CountryStretch> $stretches
     */
    private function append(array &$stretches, string $country, float $from, float $to): void
    {
        if ('' === $country || $to <= $from) {
            return;
        }

        $last = array_key_last($stretches);
        // only a contiguous stretch is extended: across an unknown stretch they stay two
        if (null !== $last && $stretches[$last]->country === $country && $stretches[$last]->toMeters === $from) {
            $stretches[$last] = new CountryStretch($country, $stretches[$last]->fromMeters, $to);

            return;
        }

        $stretches[] = new CountryStretch($country, $from, $to);
    }
}
