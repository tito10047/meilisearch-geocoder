<?php

namespace Tito10047\Geocoder\Search\Query;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * A country name recognized in a typed text, taken out of the searched words: "Košice, Slovensko"
 * searches "Košice" in Slovakia.
 *
 * A country name may also be a part of a name ("Via Italia", "Hotel Austria", "Włochy" in
 * Warszawa): the literal reading keeps it in the searched words, and the words typed around it
 * (in the same comma-separated part) tell which document would be named by it.
 */
final readonly class RecognizedCountry
{
    /**
     * @param list<string> $countries ISO 3166-1 alpha-2, upper case: the countries the name names (usually one)
     * @param string       $name      the country name as typed: "Slovensko", "Česká republika", "SK"
     * @param ParsedQuery  $literal   the text read with the country name kept in the searched words
     * @param string       $before    the words typed right before the name, in the same part of the text ("Kosice" of "Kosice Slovakia"); "" for none
     * @param string       $after     the words typed right after it, in the same part ("Kosice" of "Slovakia Kosice"); "" for none
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public function __construct(
        public array $countries,
        public string $name,
        public ParsedQuery $literal,
        public string $before = '',
        public string $after = '',
    ) {
        if ([] === $countries || !array_is_list($countries)) {
            throw new InvalidArgumentException('A recognized country names a non-empty list of countries.');
        }
        foreach ($countries as $country) {
            if (!\is_string($country) || 1 !== preg_match('/^[A-Z]{2}$/D', $country)) {
                throw new InvalidArgumentException(\sprintf('A country must be an ISO 3166-1 alpha-2 code in upper case, %s given.', get_debug_type($country)));
            }
        }
        if ('' === trim($name)) {
            throw new InvalidArgumentException('The name of a recognized country must not be empty.');
        }
    }

    /**
     * Whether the name was typed as a part of the text of its own ("Košice, Slovensko"), not among other words.
     */
    public function standsAlone(): bool
    {
        return '' === trim($this->before) && '' === trim($this->after);
    }
}
