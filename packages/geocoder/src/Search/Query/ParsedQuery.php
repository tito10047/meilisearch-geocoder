<?php

namespace Tito10047\Geocoder\Search\Query;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * The text typed by the user, split into what is searched by name and the house number.
 */
final readonly class ParsedQuery
{
    /**
     * The whole normalized text, the house number included where it was typed: "Hlavná 12 Košice".
     */
    public string $fullText;

    /**
     * @param string      $text        the normalized text to search by name, without the house number: "Hlavná Košice"
     * @param string|null $houseNumber the house number typed: "12", "12a", "606/42"; null when none
     * @param string|null $fullText    the whole normalized text; null for the text followed by the house number
     *
     * @throws InvalidArgumentException when the text is empty or the house number is empty
     */
    public function __construct(
        public string $text,
        public ?string $houseNumber = null,
        ?string $fullText = null,
    ) {
        if ('' === trim($text)) {
            throw new InvalidArgumentException('The text of a query must not be empty.');
        }
        if (null !== $houseNumber && '' === trim($houseNumber)) {
            throw new InvalidArgumentException('The house number of a query must not be empty, use null for none.');
        }

        $this->fullText = $fullText ?? (null === $houseNumber ? $text : $text.' '.$houseNumber);
    }
}
