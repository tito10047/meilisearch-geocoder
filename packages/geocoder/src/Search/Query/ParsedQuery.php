<?php

namespace Tito10047\Geocoder\Search\Query;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * The text typed by the user, split into what is searched by name, the house number, the postcode
 * and the country.
 */
final readonly class ParsedQuery
{
    /**
     * The whole normalized text, the house number included where it was typed: "Hlavná 12 Košice";
     * without the postcode and the recognized country, which are not searched.
     */
    public string $fullText;

    /**
     * @param string                 $text        the normalized text to search by name, without the house number, the postcode and the country: "Hlavná Košice"
     * @param string|null            $houseNumber the house number typed: "12", "12a", "606/42"; null when none
     * @param string|null            $fullText    the whole normalized text; null for the text followed by the house number
     * @param string|null            $postcode    the postcode typed, as typed: "040 01", "1010", "31-019"; null when none
     * @param RecognizedCountry|null $country     the country typed: "Košice, Slovensko"; null when none
     *
     * @throws InvalidArgumentException when the text is empty or the house number or the postcode is empty
     */
    public function __construct(
        public string $text,
        public ?string $houseNumber = null,
        ?string $fullText = null,
        public ?string $postcode = null,
        public ?RecognizedCountry $country = null,
    ) {
        if ('' === trim($text)) {
            throw new InvalidArgumentException('The text of a query must not be empty.');
        }
        if (null !== $houseNumber && '' === trim($houseNumber)) {
            throw new InvalidArgumentException('The house number of a query must not be empty, use null for none.');
        }
        if (null !== $postcode && '' === trim($postcode)) {
            throw new InvalidArgumentException('The postcode of a query must not be empty, use null for none.');
        }

        $this->fullText = $fullText ?? (null === $houseNumber ? $text : $text.' '.$houseNumber);
    }

    /**
     * The countries the typed country names: only their places are searched; [] for none typed.
     *
     * @return list<string>
     */
    public function countries(): array
    {
        return $this->country->countries ?? [];
    }

    /**
     * The whole text searched by name, the house number being a part of the name ("Praha 11",
     * "Wien 3"); the postcode and the country stay recognized.
     */
    public function withoutHouseNumber(): self
    {
        return new self($this->fullText, null, null, $this->postcode, $this->country);
    }
}
