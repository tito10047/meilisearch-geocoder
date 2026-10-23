<?php

namespace Tito10047\Geocoder\HouseNumber;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\StreetKey;

/**
 * One house number of a street, as stored in the database.
 */
final readonly class HouseNumber
{
    /**
     * The orientation number of a Czech or Slovak "conscription/orientation" number: "42" of "606/42".
     */
    public ?string $orientation;

    /**
     * @param string $number the number as written on the house: "12", "12a", "606/42"
     *
     * @throws InvalidArgumentException when the number is empty
     */
    public function __construct(
        public StreetKey $streetKey,
        public string $number,
        public ?string $postcode,
        public Coordinates $coordinates,
    ) {
        if ('' === trim($number)) {
            throw new InvalidArgumentException('A house number must not be empty.');
        }

        $this->orientation = 1 === preg_match('#^[^/]+/([^/]+)$#D', $number, $matches) ? $matches[1] : null;
    }
}
