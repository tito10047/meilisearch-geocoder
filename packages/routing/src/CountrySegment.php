<?php

namespace Tito10047\Routing;

use Tito10047\Routing\Exception\InvalidArgumentException;

/**
 * A distance driven in one country: a stretch of a route, or the sum of its tolled roads there.
 */
final readonly class CountrySegment
{
    /**
     * @param string $country        ISO 3166-1 alpha-2 code in upper case, e.g. "SK"
     * @param int    $distanceMeters 0 or more
     *
     * @throws InvalidArgumentException when the code is not two upper case letters or the distance is negative
     */
    public function __construct(
        public string $country,
        public int $distanceMeters,
    ) {
        if (1 !== preg_match('/^[A-Z]{2}$/D', $country)) {
            throw new InvalidArgumentException(\sprintf('The country must be an ISO 3166-1 alpha-2 code in upper case, "%s" given.', $country));
        }
        if ($distanceMeters < 0) {
            throw new InvalidArgumentException(\sprintf('The distance must not be negative, %d given.', $distanceMeters));
        }
    }
}
