<?php

namespace Tito10047\Geocoder\Photon;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * The area of the "bbox" filter of Photon, in WGS 84 degrees.
 *
 * A box crossing the antimeridian is not supported: the minimum longitude is the western edge.
 */
final readonly class BoundingBox
{
    /**
     * @throws InvalidArgumentException when a value is out of range or a minimum exceeds its maximum
     */
    public function __construct(
        public float $minLongitude,
        public float $minLatitude,
        public float $maxLongitude,
        public float $maxLatitude,
    ) {
        // the corners validate the ranges
        new Coordinates($minLatitude, $minLongitude);
        new Coordinates($maxLatitude, $maxLongitude);
        if ($minLongitude > $maxLongitude || $minLatitude > $maxLatitude) {
            throw new InvalidArgumentException('The minimum of a bounding box must not exceed its maximum.');
        }
    }

    /**
     * "minLon,minLat,maxLon,maxLat" as Photon expects it; swapped corners are put in order (Photon accepts them too).
     *
     * @throws InvalidArgumentException when the text is not four numbers in range
     */
    public static function fromString(string $box): self
    {
        $values = array_map(trim(...), explode(',', $box));
        if (4 !== \count($values) || [] !== array_filter($values, static fn (string $value): bool => !is_numeric($value))) {
            throw new InvalidArgumentException(\sprintf('The bounding box must be "minLon,minLat,maxLon,maxLat", "%s" given.', $box));
        }
        [$lon1, $lat1, $lon2, $lat2] = array_map(floatval(...), $values);

        return new self(min($lon1, $lon2), min($lat1, $lat2), max($lon1, $lon2), max($lat1, $lat2));
    }

    public function contains(Coordinates $coordinates): bool
    {
        return $coordinates->longitude >= $this->minLongitude && $coordinates->longitude <= $this->maxLongitude
            && $coordinates->latitude >= $this->minLatitude && $coordinates->latitude <= $this->maxLatitude;
    }
}
