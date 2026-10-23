<?php

namespace Tito10047\Routing\Valhalla;

use Tito10047\Routing\Exception\InvalidArgumentException;

/**
 * A stretch of a leg inside one country, by its distance from the start of the leg.
 */
final readonly class CountryStretch
{
    /**
     * @param string $country    ISO 3166-1 alpha-2 code in upper case
     * @param float  $fromMeters where the stretch begins, from the start of the leg
     * @param float  $toMeters   where it ends, not before it begins
     *
     * @throws InvalidArgumentException when the code is not two upper case letters or the positions are invalid
     */
    public function __construct(
        public string $country,
        public float $fromMeters,
        public float $toMeters,
    ) {
        if (1 !== preg_match('/^[A-Z]{2}$/D', $country)) {
            throw new InvalidArgumentException(\sprintf('The country must be an ISO 3166-1 alpha-2 code in upper case, "%s" given.', $country));
        }
        if (!is_finite($fromMeters) || !is_finite($toMeters) || $fromMeters < 0.0 || $toMeters < $fromMeters) {
            throw new InvalidArgumentException(\sprintf('A stretch must go forward from 0 or more metres, %F to %F given.', $fromMeters, $toMeters));
        }
    }

    public function lengthMeters(): float
    {
        return $this->toMeters - $this->fromMeters;
    }
}
