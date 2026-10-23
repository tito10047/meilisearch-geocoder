<?php

namespace Tito10047\Geocoder;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * Identifies a street within its town: links the street document in Meilisearch ("s<key>") to its
 * house numbers in the database.
 *
 * The key is the first 15 hexadecimal digits of xxh64("CC|town|street"): 60 bits, a positive
 * integer that fits a signed 64-bit BIGINT column.
 */
final readonly class StreetKey
{
    private const DOCUMENT_ID_PREFIX = 's';
    private const MAX = 0xFFFFFFFFFFFFFFF;

    /**
     * @throws InvalidArgumentException when the value is not a 60-bit positive integer
     */
    public function __construct(
        public int $value,
    ) {
        if ($value < 0 || $value > self::MAX) {
            throw new InvalidArgumentException(\sprintf('A street key is a 60-bit positive integer, %d given.', $value));
        }
    }

    /**
     * The key of a street, from its address as the Photon dump spells it.
     *
     * @param string $countryCode ISO 3166-1 alpha-2, in any case
     * @param string $town        the town of the address ("" when the address has none)
     * @param string $street      the street of the address, or its place when it has no street
     */
    public static function fromAddress(string $countryCode, string $town, string $street): self
    {
        $hash = hash('xxh64', strtoupper($countryCode).'|'.$town.'|'.$street);

        return new self((int) hexdec(substr($hash, 0, 15)));
    }

    /**
     * Parses the id of a street document, e.g. "s791028875740832172".
     *
     * @throws InvalidArgumentException when the id is not the id of a street document
     */
    public static function fromDocumentId(string $id): self
    {
        if (1 !== preg_match('/^'.self::DOCUMENT_ID_PREFIX.'(0|[1-9]\d{0,18})$/D', $id, $matches)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not the id of a street document.', $id));
        }

        $value = filter_var($matches[1], \FILTER_VALIDATE_INT);
        if (false === $value) {
            throw new InvalidArgumentException(\sprintf('"%s" is not the id of a street document.', $id));
        }

        return new self($value);
    }

    /**
     * The id of the street document in Meilisearch, e.g. "s791028875740832172".
     */
    public function documentId(): string
    {
        return self::DOCUMENT_ID_PREFIX.$this->value;
    }
}
