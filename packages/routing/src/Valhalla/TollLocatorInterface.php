<?php

namespace Tito10047\Routing\Valhalla;

/**
 * Finds the tolled stretches of a leg and the country of each.
 */
interface TollLocatorInterface
{
    /**
     * @param list<CountryStretch> $countries the countries of the leg, as located by a {@see CountryLocatorInterface}
     *
     * @return list<CountryStretch> the tolled stretches in the order driven, each inside one country
     */
    public function locate(Leg $leg, array $countries): array;
}
