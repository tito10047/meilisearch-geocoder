<?php

namespace Tito10047\Routing\Geometry;

use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Exception\InvalidArgumentException;

/**
 * Decodes Google's encoded polyline algorithm format at a given precision.
 *
 * Valhalla encodes its shapes with 6 decimal places ("polyline6"), Google and OSRM with 5.
 *
 * @see https://developers.google.com/maps/documentation/utilities/polylinealgorithm
 */
final readonly class PolylineDecoder implements PolylineDecoderInterface
{
    private float $factor;

    /**
     * @param int $precision decimal places of the encoded coordinates, 1 to 10
     *
     * @throws InvalidArgumentException when the precision is out of its range
     */
    public function __construct(int $precision = 6)
    {
        if ($precision < 1 || $precision > 10) {
            throw new InvalidArgumentException(\sprintf('The precision must be between 1 and 10, %d given.', $precision));
        }
        $this->factor = 10 ** $precision;
    }

    public function decode(string $encoded): array
    {
        $points = [];
        $length = \strlen($encoded);
        $offset = 0;
        $latitude = 0;
        $longitude = 0;

        while ($offset < $length) {
            $latitude += $this->readValue($encoded, $offset, $length);
            $longitude += $this->readValue($encoded, $offset, $length);
            $points[] = new Coordinates($latitude / $this->factor, $longitude / $this->factor);
        }

        return $points;
    }

    /**
     * Reads one zig-zag encoded signed value made of 5-bit chunks, the lowest first.
     */
    private function readValue(string $encoded, int &$offset, int $length): int
    {
        $result = 0;
        $shift = 0;
        do {
            if ($offset >= $length) {
                throw new InvalidArgumentException('The encoded polyline ends in the middle of a value.');
            }
            $byte = \ord($encoded[$offset++]) - 63;
            if ($byte < 0 || $byte > 63) {
                throw new InvalidArgumentException(\sprintf('The encoded polyline contains an invalid character at offset %d.', $offset - 1));
            }
            // a 64-bit integer holds 12 chunks; more could only come from a corrupted line
            if ($shift > 55) {
                throw new InvalidArgumentException('The encoded polyline contains a value that is too large.');
            }
            $result |= ($byte & 0x1F) << $shift;
            $shift += 5;
        } while ($byte >= 0x20);

        return ($result & 1) ? ~($result >> 1) : $result >> 1;
    }
}
