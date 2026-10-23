<?php

namespace Tito10047\Routing;

use Tito10047\Routing\Exception\InvalidArgumentException;

/**
 * A point on the Earth in WGS 84 degrees.
 *
 * The routing client has its own class so that it does not depend on the geocoder; both have the
 * same shape.
 */
final readonly class Coordinates
{
    /**
     * @param float $latitude  -90 to 90
     * @param float $longitude -180 to 180
     *
     * @throws InvalidArgumentException when a value is out of its range or not a finite number
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if (!is_finite($latitude) || $latitude < -90.0 || $latitude > 90.0) {
            throw new InvalidArgumentException(\sprintf('The latitude must be between -90 and 90, %F given.', $latitude));
        }
        if (!is_finite($longitude) || $longitude < -180.0 || $longitude > 180.0) {
            throw new InvalidArgumentException(\sprintf('The longitude must be between -180 and 180, %F given.', $longitude));
        }
    }
}
