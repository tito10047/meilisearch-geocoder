<?php

namespace Tito10047\Routing\Valhalla;

/**
 * Splits a leg into the countries it goes through.
 */
interface CountryLocatorInterface
{
    /**
     * @return list<CountryStretch> in the order driven, consecutive stretches always in different countries;
     *                              empty when the leg says nothing about countries
     */
    public function locate(Leg $leg): array;
}
