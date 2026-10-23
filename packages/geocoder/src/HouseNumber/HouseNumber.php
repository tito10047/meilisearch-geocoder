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
    /** The longest reference the tables store, in bytes. */
    public const MAX_REFERENCE_LENGTH = 32;

    /**
     * The orientation number of a Czech or Slovak "conscription/orientation" number: "42" of "606/42".
     */
    public ?string $orientation;

    /**
     * @param string      $number    the number as written on the house: "12", "12a", "606/42"
     * @param string|null $suburb    the part of the town the house is in, when its address names one: the
     *                               houses of a long street may lie in several ("Tatranská Lomnica", "Tatranské Matliare")
     * @param string|null $reference the official id of the address when the data has one: in Slovakia the id of the
     *                               register of addresses ("ref:minvskaddress"), see SelectionRules::$referenceKeys
     *
     * @throws InvalidArgumentException when the number or the suburb is empty, or the reference empty or longer than MAX_REFERENCE_LENGTH
     */
    public function __construct(
        public StreetKey $streetKey,
        public string $number,
        public ?string $postcode,
        public Coordinates $coordinates,
        public ?string $suburb = null,
        public ?string $reference = null,
    ) {
        if ('' === trim($number)) {
            throw new InvalidArgumentException('A house number must not be empty.');
        }
        if (null !== $suburb && '' === trim($suburb)) {
            throw new InvalidArgumentException('The suburb of a house number must not be empty: null when there is none.');
        }
        if (null !== $reference && !self::isValidReference($reference)) {
            throw new InvalidArgumentException(\sprintf('The reference of an address must be a non-empty string of at most %d bytes, "%s" given.', self::MAX_REFERENCE_LENGTH, $reference));
        }

        $this->orientation = 1 === preg_match('#^[^/]+/([^/]+)$#D', $number, $matches) ? $matches[1] : null;
    }

    /**
     * Whether the reference fits the tables; the import leaves out one that does not.
     */
    public static function isValidReference(string $reference): bool
    {
        return '' !== trim($reference) && \strlen($reference) <= self::MAX_REFERENCE_LENGTH;
    }
}
